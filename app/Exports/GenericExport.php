<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports any already-filtered Eloquent query to a spreadsheet using a
 * plain column-key => heading-label map - no per-model export class
 * needed. See App\Contracts\Exportable.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class GenericExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $columns
     */
    public function __construct(
        protected Builder $query,
        protected array $columns
    ) {}

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values($this->columns);
    }

    /**
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return collect(array_keys($this->columns))
            ->map(fn (string $key) => data_get($row, $key))
            ->all();
    }
}
