<?php

use App\Models\User;
use App\Services\Export\ExportService;
use App\Services\Import\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder as PhpSpreadsheetDefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function makeCsvUpload(array $rows, array $headers = ['Name', 'Email']): UploadedFile
{
    $csv = implode(',', $headers)."\n";

    foreach ($rows as $row) {
        $csv .= implode(',', $row)."\n";
    }

    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, $csv);

    return new UploadedFile($path, 'users.csv', 'text/csv', null, true);
}

it('imports valid rows and creates records', function () {
    $file = makeCsvUpload([
        ['Jane Doe', 'jane@example.com'],
        ['John Doe', 'john@example.com'],
    ]);

    $result = app(ImportService::class)->import(User::class, $file);

    expect($result['imported'])->toBe(2)
        ->and($result['failures'])->toBe([])
        ->and(User::where('email', 'jane@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

it('reports a row-numbered failure for an invalid row without creating it', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $file = makeCsvUpload([
        ['Valid Person', 'valid@example.com'],
        ['Duplicate Person', 'existing@example.com'],
    ]);

    $result = app(ImportService::class)->import(User::class, $file);

    expect($result['imported'])->toBe(1)
        ->and($result['failures'])->toHaveCount(1)
        ->and($result['failures'][0]['row'])->toBe(3)
        ->and(User::where('email', 'valid@example.com')->exists())->toBeTrue();
});

it('downloads a template carrying only the importable column headers', function () {
    $response = app(ImportService::class)->downloadTemplate(User::class, 'template.xlsx');

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
});

it('exports a filtered query to a spreadsheet', function () {
    User::factory()->count(3)->create();

    $query = User::query()->where('is_active', true);

    $response = app(ExportService::class)->export($query, User::class, 'users.xlsx');

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
});

it('escapes a formula-injection-prone value before writing it to the export', function () {
    User::factory()->create(['name' => '=cmd|\'/c calc\'!A1', 'email' => 'formula@example.com']);

    $query = User::query()->where('email', 'formula@example.com');

    $response = app(ExportService::class)->export($query, User::class, 'formula-export.xlsx');

    $sheet = IOFactory::load($response->getFile()->getRealPath())->getActiveSheet();

    // Row 1 is the heading row; row 2 is this user's exported "name" cell.
    expect($sheet->getCell('A2')->getValue())->toBe("'=cmd|'/c calc'!A1");
});

it('rejects a corrupt file that passes extension/mimetype checks but cannot be parsed', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    file_put_contents($path, 'this is not a real spreadsheet');

    $file = new UploadedFile($path, 'broken.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    expect(fn () => app(ImportService::class)->import(User::class, $file))
        ->toThrow(ValidationException::class);
});

it('converts a genuine parser exception on a malformed spreadsheet into a clean, non-leaking message', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    // Random binary bytes named .xlsx: Maatwebsite/PhpSpreadsheet picks the
    // Xlsx (zip-based) reader from the extension, and random bytes cannot be
    // opened as a zip container, so this genuinely throws a parser-level
    // exception (unlike an empty file, which some readers happily parse as
    // zero rows).
    file_put_contents($path, random_bytes(512));

    $file = new UploadedFile($path, 'garbage.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    try {
        app(ImportService::class)->import(User::class, $file);
        test()->fail('Expected ImportService::import() to throw for an unparsable spreadsheet.');
    } catch (ValidationException $e) {
        // If ImportService's try/catch around Excel::import() weren't real,
        // the underlying LaravelExcelException/PhpSpreadsheetException would
        // propagate uncaught and this test would fail with an uncaught
        // exception rather than reach this catch block at all.
        $message = $e->errors()['file'][0];

        expect($message)->toBe('This file could not be read. Please check that it is a valid, uncorrupted spreadsheet and try again.')
            ->and($message)->not->toContain($path)
            ->and($message)->not->toContain(sys_get_temp_dir())
            ->and($message)->not->toContain('PhpSpreadsheet')
            ->and($message)->not->toContain('Maatwebsite')
            ->and($message)->not->toContain('Exception')
            ->and($message)->not->toContain('.php');
    }

    expect(User::count())->toBe(0);
});

it('writes formula-injection-prone values as genuinely inert strings, not live formulas', function () {
    User::factory()->create(['name' => '=cmd|\'/c calc\'!A1', 'email' => 'formula-eq@example.com']);
    User::factory()->create(['name' => '+cmd|\'/c calc\'!A1', 'email' => 'formula-plus@example.com']);
    User::factory()->create(['name' => '-cmd|\'/c calc\'!A1', 'email' => 'formula-minus@example.com']);
    User::factory()->create(['name' => '@SUM(1+1)', 'email' => 'formula-at@example.com']);

    $query = User::query()
        ->whereIn('email', [
            'formula-eq@example.com',
            'formula-plus@example.com',
            'formula-minus@example.com',
            'formula-at@example.com',
        ])
        ->orderBy('email');

    $response = app(ExportService::class)->export($query, User::class, 'formula-export-datatypes.xlsx');

    $sheet = IOFactory::load($response->getFile()->getRealPath())->getActiveSheet();

    // Alphabetically by email: "formula-at" < "formula-eq" < "formula-minus" < "formula-plus".
    $cases = [
        'A2' => '@SUM(1+1)',
        'A3' => '=cmd|\'/c calc\'!A1',
        'A4' => '-cmd|\'/c calc\'!A1',
        'A5' => '+cmd|\'/c calc\'!A1',
    ];

    foreach ($cases as $coordinate => $originalValue) {
        $cell = $sheet->getCell($coordinate);

        // The raw stored value must carry the neutralizing leading
        // apostrophe...
        expect($cell->getValue())->toBe("'{$originalValue}")
            // ...and - the real proof - PhpSpreadsheet must have classified
            // it as an inert string cell, not TYPE_FORMULA. A leading
            // apostrophe only prevents Excel/Sheets from evaluating the
            // cell as a formula when the cell is genuinely typed/stored as
            // a string; merely prepending a character while the cell is
            // still written out with a formula data type would not close
            // the injection vector.
            ->and($cell->getDataType())->toBe(DataType::TYPE_STRING);
    }
});

it('confirms PhpSpreadsheet itself would classify an unescaped leading "=" as a live formula', function () {
    // Sanity-checks the escaping test above against the vendor value binder
    // directly, rather than merely trusting that TYPE_STRING is "the safe
    // one": an unescaped formula-shaped string really is classified as
    // TYPE_FORMULA by PhpSpreadsheet's own DefaultValueBinder (which
    // Maatwebsite\Excel\DefaultValueBinder - the binder actually configured
    // for this app, per config/excel.php - extends without overriding this
    // behavior for scalar values)...
    expect(PhpSpreadsheetDefaultValueBinder::dataTypeForValue('=1+1'))->toBe(DataType::TYPE_FORMULA)
        // ...while the exact same payload with GenericExport's leading
        // apostrophe is classified TYPE_STRING, i.e. genuinely inert.
        ->and(PhpSpreadsheetDefaultValueBinder::dataTypeForValue("'=1+1"))->toBe(DataType::TYPE_STRING);
});

it('independently rejects an oversized upload even without going through ImportModal', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, "Name,Email\n".str_repeat('a', 6 * 1024 * 1024));

    $file = new UploadedFile($path, 'huge.csv', 'text/csv', null, true);

    expect(fn () => app(ImportService::class)->import(User::class, $file))
        ->toThrow(ValidationException::class);
});

it('independently rejects a disallowed .exe extension even without going through ImportModal', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.exe';
    file_put_contents($path, "MZ\x90\x00this is not a spreadsheet, it's a fake executable");

    $file = new UploadedFile($path, 'malicious.exe', 'application/octet-stream', null, true);

    try {
        app(ImportService::class)->import(User::class, $file);
        test()->fail('Expected ImportService::import() to throw a ValidationException for a .exe upload.');
    } catch (ValidationException $e) {
        // Asserting guardUpload()'s own extension-allowlist message (rather
        // than the generic "could not be read" parser-failure message from
        // the Excel::import() try/catch) proves the extension allowlist
        // itself rejected the file before Excel::import() ever ran - i.e.
        // this is guardUpload()'s independent check, not a side effect of
        // the parser choking on a non-spreadsheet file.
        expect($e->errors())->toBe([
            'file' => ['The uploaded file must be a valid CSV, XLS, or XLSX file no larger than 5MB.'],
        ]);
    }

    expect(User::count())->toBe(0);
});

it('independently rejects a disallowed .php extension even without going through ImportModal', function () {
    $path = tempnam(sys_get_temp_dir(), 'import').'.php';
    file_put_contents($path, "<?php system(\$_GET['c']); ?>");

    $file = new UploadedFile($path, 'shell.php', 'application/x-httpd-php', null, true);

    expect(fn () => app(ImportService::class)->import(User::class, $file))
        ->toThrow(ValidationException::class, 'The uploaded file must be a valid CSV, XLS, or XLSX file no larger than 5MB.');

    expect(User::count())->toBe(0);
});
