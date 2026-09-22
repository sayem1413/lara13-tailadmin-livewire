<?php

use App\Services\Pdf\PdfService;

it('streams a view as a downloadable pdf', function () {
    $response = app(PdfService::class)->streamFromView('pdf.users', ['users' => collect()], 'test.pdf');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($content)->toStartWith('%PDF-');
});
