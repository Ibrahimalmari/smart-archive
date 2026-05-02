<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:SuperAdmin,Admin,Manager,Employee,Auditor',
            'organization_id' => 'required_if:role,Admin,Manager,Employee,Auditor|nullable|exists:organizations,id',
            'department_id' => 'required_if:role,Manager,Employee,Auditor|nullable|exists:departments,id',
        ];
    }
}
