<?php

namespace App\Services\Export;

use App\Contracts\Exportable;
use App\Exports\GenericExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{
    /**
     * @template TModel of Model&Exportable
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $modelClass
     */
    public function export(Builder $query, string $modelClass, string $filename): BinaryFileResponse
    {
        return Excel::download(new GenericExport($query, $modelClass::exportColumns()), $filename);
    }
}
