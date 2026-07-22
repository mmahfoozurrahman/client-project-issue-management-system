<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;

class RichTextSanitizer
{
    private const MAX_BYTES = 16_000_000;

    /**
     * Keep only the small, intentional subset of HTML supported by the editor.
     * Unrecognised elements are unwrapped, so pasted Office markup retains its text
     * without bringing across styles, embedded data, or executable attributes.
     */
    public function sanitize(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="rich-text-root">'.$html.'</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }

        $root = $document->getElementById('rich-text-root');
        if (! $root) {
            return null;
        }

        $this->sanitizeChildren($root);
        $clean = trim($this->innerHtml($root));

        if ($clean === '') {
            return null;
        }

        if (strlen($clean) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'description' => 'The description is too large. Please attach the document instead.',
            ]);
        }

        return $clean;
    }

    private function sanitizeChildren(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null; $child = $next) {
            $next = $child->nextSibling;

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }

            $this->sanitizeChildren($child);

            if (! in_array($tag, ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a'], true)) {
                $this->unwrap($child);
                continue;
            }

            $this->sanitizeAttributes($child, $tag);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $href = $element->getAttribute('href');
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $name) {
            $element->removeAttribute($name);
        }

        if ($tag !== 'a') {
            return;
        }

        $href = trim($href);
        if ($href !== '' && filter_var($href, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $href)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'nofollow noopener noreferrer');
        } else {
            $this->unwrap($element);
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
