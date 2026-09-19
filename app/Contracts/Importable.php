<?php

namespace App\Contracts;

/**
 * Implemented by any Eloquent model that can be bulk-created from an
 * uploaded spreadsheet via the shared Import modal (see ImportService,
 * GenericImport, and <x-import.button>).
 */
interface Importable
{
    /**
     * Column key => heading label. The key must be exactly what
     * Maatwebsite\Excel's WithHeadingRow derives from the label (its own
     * snake_cased, non-alphanumeric-stripped form) - e.g. "Full Name"
     * becomes "full_name" - since that's the array key each imported row
     * is keyed by.
     *
     * @return array<string, string>
     */
    public static function importColumns(): array;

    /**
     * Validation rules for one imported row, keyed the same as
     * importColumns().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function importRules(): array;

    /**
     * Build an unsaved model instance from one validated imported row -
     * the caller (GenericImport) persists it.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromImportRow(array $row): static;
}
