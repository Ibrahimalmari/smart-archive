<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $departmentId = $this->route('id');
        $department = \App\Models\Department::findOrFail($departmentId);

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->where(function ($query) use ($department) {
                    return $query->where('organization_id', $department->organization_id);
                })->ignore($departmentId) // استثن القسم الحالي من الفحص
            ],
            'code' => 'sometimes|required|string|max:50',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
            'status' => 'sometimes|nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'هذا القسم موجود بالفعل داخل هذه المنظمة. لا يمكن استخدام نفس الاسم.',
            'name.required' => 'اسم القسم مطلوب.',
            'code.required' => 'رمز القسم مطلوب.',
            'organization_id.exists' => 'المنظمة المختارة غير موجودة.',
        ];
    }
}
