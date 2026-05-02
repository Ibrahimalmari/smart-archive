<?php

namespace Tests\Unit;

use App\Http\Services\Document\OcrTextNormalizer;
use Tests\TestCase;

class OcrTextNormalizerTest extends TestCase
{
    public function test_it_repairs_common_broken_arabic_utf8_sequences(): void
    {
        $normalizer = new OcrTextNormalizer();

        $result = $normalizer->normalize('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©');

        $this->assertSame('العربية', $result);
    }

    public function test_it_preserves_valid_arabic_text(): void
    {
        $normalizer = new OcrTextNormalizer();

        $result = $normalizer->normalize("  مرحبا   بالعالم \r\n");

        $this->assertSame('مرحبا بالعالم', $result);
    }

    public function test_it_builds_display_safe_text_for_mixed_arabic_and_english(): void
    {
        $normalizer = new OcrTextNormalizer();

        $result = $normalizer->formatForDisplay("المركز الوطني\nNational Center");

        $this->assertStringContainsString("\u{202B}", $result);
        $this->assertStringContainsString("\u{202A}", $result);
    }

    public function test_it_inserts_spacing_between_arabic_and_latin_segments(): void
    {
        $normalizer = new OcrTextNormalizer();

        $result = $normalizer->normalize('رقمالصادرED01100-1100507700المركز');

        $this->assertSame('رقمالصادر ED01100 - 1100507700 المركز', $result);
    }
}
