<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('file')) {
            return;
        }

        foreach ($this->files->all() as $uploaded) {
            $file = $this->firstUploadedFile($uploaded);

            if ($file instanceof UploadedFile) {
                $this->files->set('file', $file);
                return;
            }
        }
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

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        $payload = [
            'message' => 'Validation failed.',
            'errors' => $errors,
        ];

        if (array_key_exists('file', $errors)) {
            $payload['upload_debug'] = [
                'received_file_keys' => array_keys($this->files->all()),
                'received_files' => $this->describeUploadedFiles($this->files->all()),
                'content_type' => $this->headers->get('content-type'),
                'content_length' => $this->headers->get('content-length'),
                'hint' => 'In Postman use Body > form-data, key=file, type=File. Do not add Content-Type manually.',
            ];
        }

        throw new HttpResponseException(response()->json($payload, 422));
    }

    private function firstUploadedFile(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $nestedValue) {
            $file = $this->firstUploadedFile($nestedValue);

            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    private function describeUploadedFiles(array $files): array
    {
        $details = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFile) {
                $details[$key] = [
                    'original_name' => $value->getClientOriginalName(),
                    'client_mime_type' => $value->getClientMimeType(),
                    'size' => $value->getSize(),
                    'upload_error' => $value->getError(),
                    'upload_error_message' => $value->getErrorMessage(),
                    'is_valid' => $value->isValid(),
                ];
                continue;
            }

            if (is_array($value)) {
                $details[$key] = $this->describeUploadedFiles($value);
            }
        }

        return $details;
    }
}
