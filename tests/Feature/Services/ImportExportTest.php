<?php

use App\Models\User;
use App\Services\Export\ExportService;
use App\Services\Import\ImportService;
use Illuminate\Http\UploadedFile;
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
