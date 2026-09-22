<?php

use App\Exceptions\Media\InvalidSvgException;
use App\Services\Media\SvgSanitizer;

it('removes script elements anywhere in the tree', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><g><script>alert(2)</script></g></svg>';

    $cleaned = (new SvgSanitizer)->sanitize($svg);

    expect($cleaned)->not->toContain('<script');
});

it('strips on* event-handler attributes from any element, case-insensitively', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect OnClick="alert(2)" width="1" height="1" /></svg>';

    $cleaned = (new SvgSanitizer)->sanitize($svg);

    expect($cleaned)
        ->not->toContain('onload')
        ->not->toContain('OnClick')
        ->not->toContain('onclick');
});

it('strips an href attribute that points at an external resource', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://evil.example/x" width="1" height="1" /></svg>';

    $cleaned = (new SvgSanitizer)->sanitize($svg);

    expect($cleaned)
        ->not->toContain('evil.example')
        ->not->toContain('href');
});

it('keeps a same-document fragment href', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><use href="#icon" /></svg>';

    $cleaned = (new SvgSanitizer)->sanitize($svg);

    expect($cleaned)->toContain('href="#icon"');
});

it('keeps a safe data:image URI href', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,aGVsbG8=" width="1" height="1" /></svg>';

    $cleaned = (new SvgSanitizer)->sanitize($svg);

    expect($cleaned)->toContain('data:image/png;base64,aGVsbG8=');
});

it('removes foreignObject elements', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml">html</body></foreignObject></svg>';

    $cleaned = (new SvgSanitizer)->sanitize($svg);

    expect($cleaned)
        ->not->toContain('foreignObject')
        ->not->toContain('<body');
});

it('throws when the content is not well-formed xml', function () {
    $svg = '<svg><rect></svg>';

    (new SvgSanitizer)->sanitize($svg);
})->throws(InvalidSvgException::class);

it('throws when the content is not xml at all', function () {
    $svg = 'this is definitely not svg';

    (new SvgSanitizer)->sanitize($svg);
})->throws(InvalidSvgException::class);

it('throws when the well-formed xml root element is not svg', function () {
    $svg = '<?xml version="1.0"?><not-an-svg><script>alert(1)</script></not-an-svg>';

    (new SvgSanitizer)->sanitize($svg);
})->throws(InvalidSvgException::class);
