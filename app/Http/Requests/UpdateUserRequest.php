<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => 'nullable|string',
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => 'nullable|string|min:6',
            'role' => ['nullable', Rule::in(['SuperAdmin', 'Admin', 'Manager', 'Employee', 'Auditor'])],
            'organization_id' => 'nullable|exists:organizations,id',
            'department_id' => 'nullable|exists:departments,id',
        ];
    }
}
