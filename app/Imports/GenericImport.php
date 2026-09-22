<?php

namespace App\Imports;

use App\Contracts\Importable;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Imports a spreadsheet into any model implementing App\Contracts\Importable
 * - no per-model import class needed. WithHeadingRow keys each row by its
 * header text (snake_cased), which is why Importable::importColumns()'s
 * keys must already be in that exact form.
 *
 * @template TModel of Model&Importable
 */
class GenericImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    protected int $imported = 0;

    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(
        protected string $modelClass
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public function model(array $row): Model
    {
        $this->imported++;

        return $this->modelClass::fromImportRow($row);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function rules(): array
    {
        return $this->modelClass::importRules();
    }

    public function importedCount(): int
    {
        return $this->imported;
    }
}
