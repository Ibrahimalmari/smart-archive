<?php

namespace App\Http\Services\Document;

use Illuminate\Support\Str;

class DocumentClassificationService
{
    /**
     * @var array<string, array{keywords: array<int, string>, signals: array<string, int>}>
     */
    private array $profiles = [
        'invoice' => [
            'keywords' => [
                'invoice',
                'tax invoice',
                'commercial invoice',
                'bill',
                'statement of account',
                'فاتورة',
                'فاتوره',
                'ضريبة',
                'ضريبه',
                'الاجمالي',
                'الإجمالي',
                'subtotal',
                'total due',
                'amount due',
                'unit price',
                'quantity',
            ],
            'signals' => [
                '/\binvoice\s*(no|number|#)\b/u' => 8,
                '/\bvat\b/u' => 6,
                '/\bsubtotal\b/u' => 5,
                '/\bamount due\b/u' => 6,
                '/\bunit price\b/u' => 5,
                '/\bquantity\b/u' => 4,
                '/رقم\s*الفاتور/iu' => 8,
                '/الاجمالي|الإجمالي|المجموع/iu' => 5,
                '/ضريب/iu' => 5,
                '/سعر\s*الوحد/iu' => 4,
                '/كمي/iu' => 3,
            ],
        ],
        'contract' => [
            'keywords' => [
                'contract',
                'agreement',
                'service agreement',
                'employment contract',
                'memorandum of understanding',
                'terms and conditions',
                'عقد',
                'اتفاقية',
                'اتفاقيه',
                'مذكرة تفاهم',
                'التزامات',
            ],
            'signals' => [
                '/\bthis agreement\b/u' => 8,
                '/\bparty\s*(a|b|1|2)\b/u' => 7,
                '/\beffective date\b/u' => 6,
                '/\bterm and termination\b/u' => 6,
                '/\bsigned by\b/u' => 5,
                '/الطرف\s*(الاول|الأول|الثاني)/iu' => 7,
                '/مدة\s*العقد/iu' => 6,
                '/تاريخ\s*السريان/iu' => 5,
                '/تم\s*الاتفاق/iu' => 5,
            ],
        ],
        'official_letter' => [
            'keywords' => [
                'letter',
                'official letter',
                'memo',
                'memorandum',
                'correspondence',
                'subject',
                'reference no',
                'خطاب',
                'رسالة',
                'مراسلة',
                'مذكرة',
                'الموضوع',
                'رقم الاشارة',
            ],
            'signals' => [
                '/\bsubject\b/u' => 6,
                '/\breference\s*(no|number|#)\b/u' => 6,
                '/\bdear\b/u' => 5,
                '/\bsincerely\b/u' => 5,
                '/\bministry\b/u' => 5,
                '/الموضوع/iu' => 6,
                '/المرجع|رقم\s*الاشار/iu' => 6,
                '/السيد|السادة/iu' => 5,
                '/وزارة|مديرية|هيئة/iu' => 4,
            ],
        ],
        'report' => [
            'keywords' => [
                'report',
                'monthly report',
                'progress report',
                'analysis',
                'summary',
                'findings',
                'minutes',
                'تقرير',
                'ملخص',
                'محضر',
                'تحليل',
                'نتائج',
                'توصيات',
            ],
            'signals' => [
                '/\bexecutive summary\b/u' => 7,
                '/\bfindings\b/u' => 6,
                '/\brecommendations\b/u' => 6,
                '/\bconclusion\b/u' => 5,
                '/\bminutes of meeting\b/u' => 7,
                '/ملخص\s*تنفيذي/iu' => 7,
                '/النتائج|الاستنتاجات/iu' => 6,
                '/التوصيات/iu' => 6,
                '/محضر\s*اجتماع/iu' => 7,
            ],
        ],
        'identity_document' => [
            'keywords' => [
                'passport',
                'id card',
                'national id',
                'identity',
                'resident card',
                'هوية',
                'بطاقة شخصية',
                'بطاقه شخصيه',
                'جواز سفر',
                'إقامة',
                'اقامة',
                'رقم وطني',
            ],
            'signals' => [
                '/\bpassport\s*(no|number|#)\b/u' => 8,
                '/\bdate of birth\b/u' => 6,
                '/\bnationality\b/u' => 6,
                '/\bplace of birth\b/u' => 5,
                '/رقم\s*وطني/iu' => 8,
                '/تاريخ\s*الميلاد/iu' => 6,
                '/الجنسية/iu' => 6,
                '/رقم\s*(الهويه|الهويه|الاقامه|الإقامة)/iu' => 7,
            ],
        ],
        'receipt' => [
            'keywords' => [
                'receipt',
                'payment receipt',
                'voucher',
                'cash receipt',
                'payment received',
                'إيصال',
                'ايصال',
                'سند قبض',
                'قبض',
                'استلام مبلغ',
            ],
            'signals' => [
                '/\breceipt\s*(no|number|#)\b/u' => 8,
                '/\bpayment received\b/u' => 7,
                '/\breceived from\b/u' => 6,
                '/\bcash\b/u' => 4,
                '/رقم\s*الايصال|رقم\s*الإيصال/iu' => 8,
                '/استلمنا\s*من/iu' => 6,
                '/سند\s*قبض/iu' => 7,
                '/المبلغ\s*المستلم/iu' => 6,
            ],
        ],
        'certificate' => [
            'keywords' => [
                'certificate',
                'registration certificate',
                'completion certificate',
                'attestation',
                'license',
                'certifies that',
                'شهادة',
                'شهاده',
                'ترخيص',
                'اعتماد',
                'رخصة',
                'رخصه',
            ],
            'signals' => [
                '/\bcertificate\s*(no|number|#)\b/u' => 8,
                '/\bissued on\b/u' => 5,
                '/\bvalid until\b/u' => 6,
                '/\bcertifies that\b/u' => 8,
                '/رقم\s*الشهاد/iu' => 8,
                '/صالحه?\s*لغاية|صالحة\s*لغاية/iu' => 6,
                '/تشهد\s*بأن|نشهد\s*أن/iu' => 7,
            ],
        ],
        'policy' => [
            'keywords' => [
                'policy',
                'procedure',
                'guideline',
                'manual',
                'standard operating procedure',
                'scope',
                'purpose',
                'سياسة',
                'سياسه',
                'إجراء',
                'اجراء',
                'دليل',
                'معيار',
            ],
            'signals' => [
                '/\bpurpose\b/u' => 5,
                '/\bscope\b/u' => 5,
                '/\bresponsibilities\b/u' => 6,
                '/\bprocedure\b/u' => 5,
                '/الغرض/iu' => 5,
                '/النطاق/iu' => 5,
                '/المسؤوليات|المسئوليات/iu' => 6,
                '/الاجراءات|الإجراءات/iu' => 6,
            ],
        ],
        'legal_document' => [
            'keywords' => [
                'legal',
                'lawsuit',
                'court',
                'articles of association',
                'bylaws',
                'incorporation',
                'قانوني',
                'قضية',
                'محكمة',
                'دعوى',
                'تأسيس',
                'تاسيس',
                'النظام الاساسي',
                'النظام الأساسي',
            ],
            'signals' => [
                '/\bcase\s*(no|number|#)\b/u' => 8,
                '/\bcourt\b/u' => 7,
                '/\bplaintiff\b|\bdefendant\b/u' => 7,
                '/\barticles of association\b/u' => 8,
                '/رقم\s*الدعوى/iu' => 8,
                '/محكمه?|قاضي/iu' => 7,
                '/المدعي|المدعى\s*عليه/iu' => 7,
                '/النظام\s*الاساسي|النظام\s*الأساسي/iu' => 8,
                '/تأسيس|تاسيس/iu' => 6,
            ],
        ],
    ];

    /**
     * @var array<string, int>
     */
    private array $fieldWeights = [
        'title' => 9,
        'description' => 5,
        'original_name' => 4,
        'mime_type' => 1,
        'ocr' => 7,
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public function keywordsByType(): array
    {
        $keywords = [];

        foreach ($this->profiles as $type => $profile) {
            $keywords[$type] = $profile['keywords'];
        }

        return $keywords;
    }

    /**
     * @return array{document_type:string,classification_confidence:float,classification_source:string}
     */
    public function classify(
        string $title,
        ?string $description,
        string $originalName,
        ?string $mimeType = null,
        ?string $extractedText = null
    ): array {
        $fields = [
            'title' => $this->normalizeText($title),
            'description' => $this->normalizeText((string) $description),
            'original_name' => $this->normalizeText($originalName),
            'mime_type' => $this->normalizeText((string) $mimeType),
            'ocr' => $this->normalizeText((string) $extractedText),
        ];

        $combined = trim(implode(' ', array_filter($fields)));
        $scores = [];

        foreach ($this->profiles as $type => $profile) {
            $scores[$type] = $this->keywordEvidenceScore($fields, $profile['keywords'])
                + $this->signalScore($combined, $profile['signals'])
                + $this->typeSpecificBoost($type, $fields, $combined);
        }

        arsort($scores);
        $bestType = (string) array_key_first($scores);
        $bestScore = (int) ($scores[$bestType] ?? 0);
        $runnerUp = $this->runnerUpScore($scores);

        $source = $fields['ocr'] !== '' ? 'ocr+metadata' : 'metadata';

        if ($bestScore < 6) {
            return [
                'document_type' => 'other',
                'classification_confidence' => $source === 'ocr+metadata' ? 0.28 : 0.20,
                'classification_source' => $source,
            ];
        }

        $margin = max(0, $bestScore - $runnerUp);
        $confidence = 0.45 + min(0.28, $bestScore / 45) + min(0.22, $margin / 20);
        $confidence = min(0.99, round($confidence, 2));

        return [
            'document_type' => $bestType,
            'classification_confidence' => $confidence,
            'classification_source' => $source,
        ];
    }

    private function normalizeText(string $value): string
    {
        $value = Str::lower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            ['أ', 'إ', 'آ', 'ؤ', 'ئ', 'ى', 'ة', 'ـ'],
            ['ا', 'ا', 'ا', 'و', 'ي', 'ي', 'ه', ''],
            $value
        );

        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\s#\/\.\-:]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<string, string> $fields
     * @param array<int, string> $keywords
     */
    private function keywordEvidenceScore(array $fields, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword) {
            $needle = $this->normalizeText($keyword);
            if ($needle === '') {
                continue;
            }

            $keywordWeight = mb_strlen($needle) >= 12 ? 2 : 1;

            foreach ($fields as $field => $haystack) {
                if ($haystack === '' || !str_contains($haystack, $needle)) {
                    continue;
                }

                $score += ($this->fieldWeights[$field] ?? 1) * $keywordWeight;

                if (
                    ($field === 'title' || $field === 'original_name')
                    && str_starts_with($haystack, $needle)
                ) {
                    $score += 2;
                }
            }
        }

        return $score;
    }

    /**
     * @param array<string, int> $signals
     */
    private function signalScore(string $text, array $signals): int
    {
        if ($text === '') {
            return 0;
        }

        $score = 0;

        foreach ($signals as $pattern => $boost) {
            if (preg_match($pattern, $text) === 1) {
                $score += $boost;
            }
        }

        return $score;
    }

    /**
     * @param array<string, string> $fields
     */
    private function typeSpecificBoost(string $type, array $fields, string $combined): int
    {
        if ($combined === '') {
            return 0;
        }

        return match ($type) {
            'invoice' => $this->countSignals($combined, ['usd', 'sar', 'amount', 'invoice', 'فاتوره', 'فاتورة']) >= 2 ? 4 : 0,
            'receipt' => $this->countSignals($combined, ['receipt', 'received', 'إيصال', 'ايصال', 'قبض']) >= 2 ? 4 : 0,
            'contract' => $this->countSignals($combined, ['agreement', 'contract', 'عقد', 'الطرف']) >= 2 ? 5 : 0,
            'identity_document' => $this->countSignals($combined, ['passport', 'nationality', 'هويه', 'هوية', 'الجنسيه', 'الجنسية']) >= 2 ? 5 : 0,
            'policy' => $this->countSignals($fields['title'] . ' ' . $fields['ocr'], ['policy', 'procedure', 'سياسه', 'سياسة', 'إجراء', 'اجراء']) >= 2 ? 4 : 0,
            'report' => $this->countSignals($fields['title'] . ' ' . $fields['ocr'], ['report', 'summary', 'تقرير', 'ملخص']) >= 2 ? 4 : 0,
            default => 0,
        };
    }

    /**
     * @param array<int, string> $signals
     */
    private function countSignals(string $text, array $signals): int
    {
        $count = 0;

        foreach ($signals as $signal) {
            $needle = $this->normalizeText($signal);
            if ($needle !== '' && str_contains($text, $needle)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, int> $scores
     */
    private function runnerUpScore(array $scores): int
    {
        $values = array_values($scores);

        return (int) ($values[1] ?? 0);
    }
}
