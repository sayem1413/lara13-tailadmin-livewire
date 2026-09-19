<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders any Blade view to a downloadable PDF. Wraps dompdf's own
 * download() (a plain Illuminate\Http\Response) in response()
 * ->streamDownload() instead, since Livewire only recognizes
 * StreamedResponse/BinaryFileResponse as a file-download effect - see
 * Livewire\Features\SupportFileDownloads.
 */
class PdfService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function streamFromView(string $view, array $data, string $filename): StreamedResponse
    {
        $binary = Pdf::loadView($view, $data)->output();

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
