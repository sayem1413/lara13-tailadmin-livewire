<?php

namespace App\Services\Import;

use App\Contracts\Importable;
use App\Exports\GenericTemplateExport;
use App\Imports\GenericImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportService
{
    /**
     * The only current caller (ImportModal) already validates the upload
     * against these same extensions/size before it ever reaches here, but
     * this service can't assume every future caller will - a malformed or
     * oversized file must not reach the spreadsheet parser regardless of
     * how it got here.
     *
     * @var array<int, string>
     */
    protected const ALLOWED_EXTENSIONS = ['csv', 'xls', 'xlsx'];

    /** Matches ImportModal's own dropzone limit (see its rules()). */
    protected const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    /**
     * @template TModel of Model&Importable
     *
     * @param  class-string<TModel>  $modelClass
     * @return array{imported: int, failures: array<int, array{row: int, errors: array<int, string>}>}
     */
    public function import(string $modelClass, UploadedFile $file): array
    {
        $this->guardUpload($file);

        $import = new GenericImport($modelClass);

        try {
            Excel::import($import, $file);
        } catch (LaravelExcelException|PhpSpreadsheetException) {
            // The underlying reader's own message can include local file
            // paths or raw parser internals, so it isn't safe to show
            // verbatim to the user.
            throw ValidationException::withMessages([
                'file' => 'This file could not be read. Please check that it is a valid, uncorrupted spreadsheet and try again.',
            ]);
        }

        $failures = $import->failures()
            ->groupBy(fn (Failure $failure) => $failure->row())
            ->map(fn ($group, int $row) => [
                'row' => $row,
                'errors' => $group->flatMap(fn (Failure $failure) => $failure->errors())->all(),
            ])
            ->values()
            ->all();

        return [
            'imported' => $import->importedCount(),
            'failures' => $failures,
        ];
    }

    /**
     * @template TModel of Model&Importable
     *
     * @param  class-string<TModel>  $modelClass
     */
    public function downloadTemplate(string $modelClass, string $filename): BinaryFileResponse
    {
        return Excel::download(new GenericTemplateExport($modelClass::importColumns()), $filename);
    }

    /**
     * Independently guards against a malformed/oversized upload reaching
     * the spreadsheet parser - see the ALLOWED_EXTENSIONS/MAX_UPLOAD_BYTES
     * doc comments above.
     */
    protected function guardUpload(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (
            ! $file->isValid()
            || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || $file->getSize() > self::MAX_UPLOAD_BYTES
        ) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file must be a valid CSV, XLS, or XLSX file no larger than 5MB.',
            ]);
        }
    }
}
