<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true; // السماح للجميع بطلب التسجيل
    }

    public function rules()
    {
        return [
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role'     => 'in:Admin,Manager,Employee,Auditor',
        ];
    }
}
