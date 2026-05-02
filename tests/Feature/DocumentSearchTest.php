<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_ranks_documents_using_ollama_embeddings(): void
    {
        Config::set('services.ollama.base_url', 'http://localhost:11434');
        Config::set('services.ollama.embedding_model', 'nomic-embed-text');
        Config::set('services.ollama.embedding_dimensions', 3);

        Http::fake([
            'http://localhost:11434/api/embed' => Http::sequence()
                ->push([
                    'embeddings' => [
                        [1.0, 0.0, 0.0],
                        [0.0, 1.0, 0.0],
                    ],
                ])
                ->push([
                    'embeddings' => [
                        [0.99, 0.01, 0.0],
                    ],
                ]),
        ]);

        [$organization, $department, $employee] = $this->seedScope();

        $contract = Document::create([
            'title' => 'Procurement contract',
            'description' => 'Vendor legal agreement',
            'original_name' => 'contract.pdf',
            'path' => 'documents/contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'uploaded_by' => $employee->id,
        ]);
        $contract->workflow()->create(['status' => Document::STATUS_PENDING]);

        $invoice = Document::create([
            'title' => 'Monthly expenses',
            'description' => 'Invoice summary',
            'original_name' => 'invoice.pdf',
            'path' => 'documents/invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'uploaded_by' => $employee->id,
        ]);
        $invoice->workflow()->create(['status' => Document::STATUS_PENDING]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/documents/search?q=agreement');

        $response->assertOk()
            ->assertJsonPath('search_mode', 'hybrid_embeddings')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.original_name', 'contract.pdf')
            ->assertJsonPath('results.0.search_backend', 'hybrid_embeddings');
    }

    public function test_search_falls_back_to_keyword_mode_when_ollama_is_not_configured(): void
    {
        Config::set('services.ollama.base_url', '');
        Config::set('services.ollama.embedding_model', '');

        [$organization, $department, $employee] = $this->seedScope();

        $document = Document::create([
            'title' => 'Procurement contract',
            'description' => 'Vendor legal agreement',
            'original_name' => 'contract.pdf',
            'path' => 'documents/contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'uploaded_by' => $employee->id,
        ]);
        $document->workflow()->create(['status' => Document::STATUS_PENDING]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/documents/search?q=agreement');

        $response->assertOk()
            ->assertJsonPath('search_mode', 'keyword_fallback')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.original_name', 'contract.pdf')
            ->assertJsonPath('results.0.search_backend', 'keyword_fallback');
    }

    private function seedScope(): array
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

        $employee = User::factory()->create([
            'role' => 'Employee',
            'status' => 'active',
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'email_verified_at' => now(),
        ]);

        return [$organization, $department, $employee];
    }
}
