<?php

namespace App\Http\Controllers;

use App\Http\DTOs\Organization\OrganizationDto;
use App\Http\Services\Organization\OrganizationServiceInterface;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    private OrganizationServiceInterface $organizations;

    public function __construct(OrganizationServiceInterface $organizations)
    {
        $this->organizations = $organizations;
    }

    // GET /organizations
    public function index(Request $request)
    {
        $items = $this->organizations->list(
            perPage: (int) $request->get('per_page', 15)
        );

        return response()->json($items);
    }

    // GET /organizations/{id}
    public function show(int $id)
    {
        $org = $this->organizations->find($id);

        return response()->json($org);
    }

    // POST /organizations
    public function store(StoreOrganizationRequest $request)
    {
        $data = $request->validated();

        $dto = new OrganizationDto(
            $data['name'],
            $data['country'],
            $data['city'],
            $data['type'],
            $data['address'],
            $data['status'] ?? 'active',
        );

        $org = $this->organizations->create($dto);

        return response()->json($org, 201);
    }

    // PUT /organizations/{id}
    public function update(UpdateOrganizationRequest $request, int $id)
    {
        $org = Organization::find($id);

        if (!$org) {
            return response()->json(['message' => 'المنظمة غير موجودة'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'country' => 'sometimes|required|string|max:100',
            'city' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|string',
            'address' => 'sometimes|required|string',
            'status' => 'sometimes|nullable|in:active,inactive',
        ]);

        // قراءة البيانات من $_POST و $request->all() معاً
        $allData = array_merge($_POST ?? [], $request->all());
        
        $updateData = [];
        if (!empty($allData['name'] ?? null)) {
            $updateData['name'] = trim($allData['name']);
        }
        if (!empty($allData['country'] ?? null)) {
            $updateData['country'] = trim($allData['country']);
        }
        if (!empty($allData['city'] ?? null)) {
            $updateData['city'] = trim($allData['city']);
        }
        if (!empty($allData['type'] ?? null)) {
            $updateData['type'] = trim($allData['type']);
        }
        if (!empty($allData['address'] ?? null)) {
            $updateData['address'] = trim($allData['address']);
        }
        if (!empty($allData['status'] ?? null)) {
            $updateData['status'] = trim($allData['status']);
        }
        
        if (!empty($updateData)) {
            $org->update($updateData);
        }

        return response()->json($org);
    }

    // DELETE /organizations/{id}
    public function destroy(int $id)
    {
        $this->organizations->delete($id);

        return response()->json(['message' => 'Organization deleted']);
    }
}
