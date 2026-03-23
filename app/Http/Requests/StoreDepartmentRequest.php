<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = $this->route('orgId');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where(function ($query) use ($orgId) {
                    return $query->where('organization_id', $orgId);
                }),
            ],
            'code' => 'required|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم القسم مطلوب.',
            'name.unique' => 'هذا القسم موجود بالفعل داخل هذه المنظمة. لا يمكن استخدام نفس الاسم.',
            'code.required' => 'رمز القسم مطلوب.',
            'status.in' => 'حالة القسم يجب أن تكون active أو inactive.',
        ];
    }
}

