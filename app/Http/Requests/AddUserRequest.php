<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddUserRequest extends FormRequest
{
    public function authorize()
    {
        return true; // السماح للجميع بطلب التسجيل
    }

   public function rules()
{
    return [
        'name'  => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'role' => 'required|in:SuperAdmin,Admin,Manager,Employee,Auditor',

        'organization_id' => 'required_if:role,Admin,Manager,Employee,Auditor|nullable',
        'department_id'   => 'required_if:role,Manager,Employee,Auditor|nullable',
    ];
}



}
