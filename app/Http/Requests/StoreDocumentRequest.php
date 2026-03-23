<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = Auth::user();
        $departmentId = $this->input('department_id') ?? $user->department_id;

        return [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('documents', 'title')
                    ->where('uploaded_by', $user->id)
                    ->where('department_id', $departmentId),
                // منع تكرار نفس الاسم من نفس المستخدم في نفس القسم
            ],
            'description' => 'nullable|string',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'department_id' => 'nullable|integer|exists:departments,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'الملف مطلوب.',
            'file.mimes' => 'يجب أن يكون الملف بصيغة: PDF أو JPG أو PNG.',
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت.',
            'title.required' => 'عنوان الوثيقة مطلوب.',
            'title.unique' => 'هذا العنوان موجود لديك بالفعل في هذا القسم. لا يمكن إضافة وثيقة بنفس الاسم.',
            'title.max' => 'العنوان يجب أن لا يتجاوز 255 حرف.',
            'department_id.exists' => 'القسم المختار غير موجود.',
            'organization_id.exists' => 'المنظمة المختارة غير موجودة.',
        ];
    }
}
