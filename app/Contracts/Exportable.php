<?php

namespace App\Contracts;

/**
 * Implemented by any Eloquent model that can be exported to a spreadsheet
 * via the shared Export action (see ExportService, GenericExport).
 */
interface Exportable
{
    /**
     * Column key (a model attribute, or dot-notation for a relation) =>
     * heading label, in the order columns should appear in the export.
     *
     * @return array<string, string>
     */
    public static function exportColumns(): array;
}
