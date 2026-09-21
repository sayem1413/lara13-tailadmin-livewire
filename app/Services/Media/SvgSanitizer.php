<?php

namespace App\Services\Media;

use App\Exceptions\Media\InvalidSvgException;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMXPath;

/**
 * Strips the well-known stored-XSS vectors out of an untrusted SVG upload
 * before it's written to storage. Unlike a raster image, an SVG is XML that
 * a browser will happily execute if the file is ever opened directly:
 *
 * - `<script>` elements run arbitrary JavaScript.
 * - `on*` event-handler attributes (`onload`, `onclick`, `onerror`, ...) run
 *   JavaScript without needing a `<script>` tag at all.
 * - An `href`/`xlink:href` pointing at an external URL (e.g. an `<image>`
 *   or `<use>` reference) can pull in and execute arbitrary remote content.
 * - `<foreignObject>` can embed arbitrary HTML (including `<script>`)
 *   inside otherwise-inert SVG markup - a common sandbox-escape vector.
 *
 * This is a hand-rolled sanitizer (not a Composer package) built on
 * ext-dom, which this application already has no new dependency for.
 */
class SvgSanitizer
{
    /**
     * @throws InvalidSvgException if $svgContents isn't well-formed SVG/XML.
     */
    public function sanitize(string $svgContents): string
    {
        $document = $this->parse($svgContents);

        $this->removeElementsByLocalName($document, 'script');
        $this->removeElementsByLocalName($document, 'foreignObject');
        $this->stripEventHandlerAttributes($document);
        $this->stripExternalReferences($document);

        $xml = $document->saveXML();

        if ($xml === false) {
            throw InvalidSvgException::forMalformedXml();
        }

        return $xml;
    }

    /**
     * @throws InvalidSvgException
     */
    private function parse(string $svgContents): DOMDocument
    {
        $previousSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;

        // LIBXML_NONET blocks the parser from making any network request
        // for an external reference. We deliberately don't pass
        // LIBXML_DTDLOAD/LIBXML_NOENT, so no external DTD is fetched and no
        // entity is expanded - external entity loading is no longer
        // honoured by DOMDocument by default since PHP 8 regardless (the
        // old libxml_disable_entity_loader() toggle is deprecated and now
        // a no-op), so this is defense in depth rather than the only guard.
        $loaded = $document->loadXML($svgContents, LIBXML_NONET);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if (! $loaded || $errors !== [] || ! $document->documentElement) {
            throw InvalidSvgException::forMalformedXml();
        }

        if (strtolower($document->documentElement->localName ?? '') !== 'svg') {
            throw InvalidSvgException::forMalformedXml();
        }

        return $document;
    }

    private function removeElementsByLocalName(DOMDocument $document, string $localName): void
    {
        $xpath = new DOMXPath($document);

        /** @var DOMElement $element */
        foreach ($this->query($xpath, "//*[local-name()='{$localName}']") as $element) {
            $element->parentNode?->removeChild($element);
        }
    }

    private function stripEventHandlerAttributes(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        /** @var DOMElement $element */
        foreach ($this->query($xpath, '//*') as $element) {
            /** @var DOMAttr $attribute */
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if (str_starts_with(strtolower($attribute->name), 'on')) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
    }

    private function stripExternalReferences(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        /** @var DOMAttr $attribute */
        foreach ($this->query($xpath, "//@*[local-name()='href']") as $attribute) {
            if (! $this->isSafeReference($attribute->value)) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * DOMXPath::query() is typed to allow `false` only for a malformed
     * expression - never the case here, since every call site above passes
     * one of our own fixed, valid queries - but PHPStan can't know that, so
     * this narrows it to an empty result instead of a type never actually
     * produced by these call sites.
     *
     * @return array<int, DOMNode|DOMNameSpaceNode>
     */
    private function query(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);

        return $nodes === false ? [] : iterator_to_array($nodes);
    }

    private function isSafeReference(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '#')) {
            return true;
        }

        return str_starts_with(strtolower($value), 'data:image/');
    }
}
