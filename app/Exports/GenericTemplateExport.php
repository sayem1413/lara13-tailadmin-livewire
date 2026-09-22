<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * A blank spreadsheet carrying only an Importable model's expected column
 * headers, for the Import modal's "Download Template" action.
 */
class GenericTemplateExport implements FromArray, WithHeadings
{
    /**
     * @param  array<string, string>  $columns
     */
    public function __construct(
        protected array $columns
    ) {}

    /**
     * @return array<int, array<array-key, mixed>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values($this->columns);
    }
}
