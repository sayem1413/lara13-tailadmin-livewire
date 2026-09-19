<?php

namespace App\Services\Import;

use App\Contracts\Importable;
use App\Exports\GenericTemplateExport;
use App\Imports\GenericImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportService
{
    /**
     * @template TModel of Model&Importable
     *
     * @param  class-string<TModel>  $modelClass
     * @return array{imported: int, failures: array<int, array{row: int, errors: array<int, string>}>}
     */
    public function import(string $modelClass, UploadedFile $file): array
    {
        $import = new GenericImport($modelClass);

        Excel::import($import, $file);

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
}
