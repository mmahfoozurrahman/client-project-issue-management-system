<?php

namespace Tests\Unit;

use App\Services\RichTextSanitizer;
use PHPUnit\Framework\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_sanitizer_preserves_table_structure(): void
    {
        $sanitizer = new RichTextSanitizer();

        $input = '<table><thead><tr><th>Header 1</th><th>Header 2</th></tr></thead><tbody><tr><td>Data 1</td><td>Data 2</td></tr></tbody></table>';
        $sanitized = $sanitizer->sanitize($input);

        $this->assertStringContainsString('<table>', $sanitized);
        $this->assertStringContainsString('<thead>', $sanitized);
        $this->assertStringContainsString('<tbody>', $sanitized);
        $this->assertStringContainsString('<tr>', $sanitized);
        $this->assertStringContainsString('<th>Header 1</th>', $sanitized);
        $this->assertStringContainsString('<td>Data 1</td>', $sanitized);
    }

    public function test_sanitizer_removes_disallowed_scripts_inside_tables(): void
    {
        $sanitizer = new RichTextSanitizer();

        $input = '<table><tr><td>Data <script>alert("xss")</script></td></tr></table>';
        $sanitized = $sanitizer->sanitize($input);

        $this->assertStringContainsString('<td>Data </td>', $sanitized);
        $this->assertStringNotContainsString('script', $sanitized);
    }
}
