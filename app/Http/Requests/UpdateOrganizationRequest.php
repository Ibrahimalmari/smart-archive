<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
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
        $organizationId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('organizations', 'name')->ignore($organizationId) // استثن المنظمة الحالية من الفحص
            ],
            'type' => 'sometimes|required|string|max:100',
            'country' => 'sometimes|required|string|max:100',
            'city' => 'sometimes|required|string|max:100',
            'address' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'هذه المنظمة موجودة بالفعل. لا يمكن استخدام نفس الاسم.',
            'name.required' => 'اسم المنظمة مطلوب.',
            'type.required' => 'نوع المنظمة مطلوب.',
            'country.required' => 'البلد مطلوب.',
            'city.required' => 'المدينة مطلوبة.',
            'address.required' => 'العنوان مطلوب.',
        ];
    }
}
