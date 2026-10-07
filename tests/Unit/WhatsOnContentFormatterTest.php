<?php

namespace Tests\Unit;

use App\Support\WhatsOnContentFormatter;
use PHPUnit\Framework\TestCase;

class WhatsOnContentFormatterTest extends TestCase
{
    public function test_it_preserves_allowed_formatting_and_alignment(): void
    {
        $html = '<p style="text-align: center; color: red"><strong>Judul</strong></p><ul><li>Item</li></ul>';

        $this->assertSame(
            '<p style="text-align: center;"><strong>Judul</strong></p><ul><li>Item</li></ul>',
            WhatsOnContentFormatter::sanitizeHtml($html)
        );
    }

    public function test_it_removes_scripts_and_unsafe_attributes(): void
    {
        $html = '<p onclick="alert(1)" style="text-align: right; background:url(javascript:alert(1))">Aman<script>alert(1)</script></p>';

        $this->assertSame(
            '<p style="text-align: right;">Aman</p>',
            WhatsOnContentFormatter::sanitizeHtml($html)
        );
    }

    public function test_it_converts_rich_content_to_plain_text_for_legacy_clients(): void
    {
        $this->assertSame(
            "Pagi\n\nSarapan",
            WhatsOnContentFormatter::toPlainText('<p><strong>Pagi</strong></p><p>Sarapan</p>')
        );
    }

    public function test_it_wraps_legacy_plain_text_as_safe_html(): void
    {
        $this->assertSame(
            '<p>A &amp; B</p><p>Baris 2</p>',
            WhatsOnContentFormatter::toHtml("A & B\n\nBaris 2")
        );
    }
}
