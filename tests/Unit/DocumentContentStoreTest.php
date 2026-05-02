<?php

namespace Tests\Unit;

use App\Http\Services\Document\DocumentContentStoreInterface;
use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DocumentContentStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_content_store_persists_ocr_and_embeddings(): void
    {
        Config::set('services.document_content.driver', 'database');

        $document = $this->makeDocument();

        /** @var DocumentContentStoreInterface $store */
        $store = $this->app->make(DocumentContentStoreInterface::class);

        $store->saveOcrText($document, 'Extracted OCR text');
        $document->refresh();

        $this->assertSame('database', $store->driver());
        $this->assertSame('Extracted OCR text', $store->getOcrText($document));

        $store->saveEmbedding($document, 'hash-value', 'test-model', [1.0, 0.0, 0.5]);
        $embedding = $store->getEmbedding($document->refresh());

        $this->assertSame('hash-value', $embedding['text_hash']);
        $this->assertSame('test-model', $embedding['model']);
        $this->assertSame(3, $embedding['dimensions']);
        $this->assertSame([1.0, 0.0, 0.5], $embedding['vector']);
    }

    private function makeDocument(): Document
    {
        $organization = Organization::create([
            'name' => 'Org A',
            'type' => 'company',
            'country' => 'SY',
            'city' => 'Damascus',
            'address' => 'Addr',
            'status' => 'active',
        ]);

        $department = Department::create([
            'organization_id' => $organization->id,
            'name' => 'IT',
            'code' => 'IT',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        $document = Document::create([
            'title' => 'Contract',
            'description' => 'Agreement',
            'original_name' => 'contract.pdf',
            'path' => 'documents/contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'uploaded_by' => $user->id,
        ]);

        $document->workflow()->create([
            'status' => Document::STATUS_PENDING,
        ]);

        return $document;
    }
}
