<?php

namespace Tests\Unit;

use App\Http\Services\Document\DocumentClassificationService;
use PHPUnit\Framework\TestCase;

class DocumentClassificationServiceTest extends TestCase
{
    public function test_it_classifies_invoice_from_metadata_keywords(): void
    {
        $service = new DocumentClassificationService();

        $result = $service->classify(
            title: 'فاتورة كهرباء شهرية',
            description: 'bill for office',
            originalName: 'invoice-march.pdf',
            mimeType: 'application/pdf',
            extractedText: null,
        );

        $this->assertSame('invoice', $result['document_type']);
        $this->assertGreaterThan(0.20, $result['classification_confidence']);
        $this->assertSame('metadata', $result['classification_source']);
    }

    public function test_it_falls_back_to_other_when_no_keywords_match(): void
    {
        $service = new DocumentClassificationService();

        $result = $service->classify(
            title: 'file 001',
            description: 'generic attachment',
            originalName: 'unknown.bin',
            mimeType: 'application/octet-stream',
            extractedText: null,
        );

        $this->assertSame('other', $result['document_type']);
        $this->assertSame(0.20, $result['classification_confidence']);
    }

    public function test_it_classifies_arabic_founding_document_from_title_and_ocr(): void
    {
        $service = new DocumentClassificationService();

        $result = $service->classify(
            title: 'ملف حول التاسيس جمعية',
            description: 'حول جمعية خيرية',
            originalName: 'ta2sis.pdf',
            mimeType: 'application/pdf',
            extractedText: 'قرار بخصوص التاسيس رقم 004/01/2023 بالسجل الخاص بالمؤسسات الاهلية',
        );

        $this->assertContains($result['document_type'], ['legal_document', 'official_letter', 'certificate']);
        $this->assertGreaterThan(0.20, $result['classification_confidence']);
        $this->assertSame('ocr+metadata', $result['classification_source']);
    }

    public function test_it_distinguishes_receipt_from_invoice_using_payment_signals(): void
    {
        $service = new DocumentClassificationService();

        $result = $service->classify(
            title: 'Payment Receipt',
            description: 'cash received from customer',
            originalName: 'receipt-2026.pdf',
            mimeType: 'application/pdf',
            extractedText: 'Receipt No 55 Payment received from Ahmad for the amount of 500 USD',
        );

        $this->assertSame('receipt', $result['document_type']);
        $this->assertGreaterThan(0.50, $result['classification_confidence']);
        $this->assertSame('ocr+metadata', $result['classification_source']);
    }
}
