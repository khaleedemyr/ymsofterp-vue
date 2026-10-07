<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class WhatsOnContentFormatter
{
    private const ALLOWED_TAGS = [
        'b', 'br', 'div', 'em', 'i', 'li', 'ol', 'p', 'strong', 'u', 'ul',
    ];

    private const DROP_CONTENT_TAGS = [
        'iframe', 'object', 'script', 'style', 'svg', 'template',
    ];

    public static function sanitizeHtml(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $previousErrorsState = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="whats-on-content-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );

            $root = $document->getElementById('whats-on-content-root');
            if (!$root) {
                return '';
            }

            return trim(self::renderChildren($root));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorsState);
        }
    }

    public static function toPlainText(?string $html): string
    {
        $cleanHtml = self::sanitizeHtml($html);
        $withLineBreaks = preg_replace(
            ['/<br\s*\/?>/i', '/<\/(?:div|p)>/i', '/<\/li>/i', '/<(?:div|li|p)(?:\s[^>]*)?>/i'],
            ["\n", "\n\n", "\n", ''],
            $cleanHtml
        ) ?? $cleanHtml;
        $plainText = html_entity_decode(
            strip_tags($withLineBreaks),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $plainText = preg_replace('/[ \t]+\n/', "\n", $plainText) ?? $plainText;
        $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText) ?? $plainText;

        return trim($plainText);
    }

    public static function toHtml(?string $content): string
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
            return self::sanitizeHtml($content);
        }

        return collect(preg_split('/\R\s*\R/', $content) ?: [])
            ->map(function (string $paragraph): string {
                $lines = preg_split('/\R/', $paragraph) ?: [];
                $escapedLines = array_map(
                    fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
                    $lines
                );

                return '<p>'.implode('<br>', $escapedLines).'</p>';
            })
            ->implode('');
    }

    private static function renderChildren(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= self::renderNode($child);
        }

        return $html;
    }

    private static function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        if (!$node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROP_CONTENT_TAGS, true)) {
            return '';
        }

        $children = self::renderChildren($node);
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            return $children;
        }

        if ($tag === 'br') {
            return '<br>';
        }

        $style = self::alignmentStyle($node, $tag);
        $attributes = $style ? ' style="'.$style.'"' : '';

        return '<'.$tag.$attributes.'>'.$children.'</'.$tag.'>';
    }

    private static function alignmentStyle(DOMElement $element, string $tag): string
    {
        if (!in_array($tag, ['div', 'li', 'p'], true)) {
            return '';
        }

        $style = $element->getAttribute('style');
        if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i', $style, $matches)) {
            return 'text-align: '.strtolower($matches[1]).';';
        }

        $align = strtolower($element->getAttribute('align'));
        if (in_array($align, ['left', 'center', 'right', 'justify'], true)) {
            return 'text-align: '.$align.';';
        }

        return '';
    }
}
