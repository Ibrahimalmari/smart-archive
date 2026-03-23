<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organizations', 'name') // لا يمكن تكرار نفس الاسم
            ],
            'type' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'هذه المنظمة موجودة بالفعل. لا يمكن إضافة منظمة بنفس الاسم.',
            'name.required' => 'اسم المنظمة مطلوب.',
            'type.required' => 'نوع المنظمة مطلوب.',
            'country.required' => 'البلد مطلوب.',
            'city.required' => 'المدينة مطلوبة.',
            'address.required' => 'العنوان مطلوب.',
        ];
    }
}
