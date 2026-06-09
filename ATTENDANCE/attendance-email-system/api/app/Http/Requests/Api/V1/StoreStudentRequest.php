<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roll_number'  => ['required', 'string', 'max:20', 'unique:students,roll_number'],
            'student_name' => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:100', 'unique:students,email'],
            'parent_email' => ['nullable', 'email', 'max:100'],
            'department'   => ['required', 'string', 'max:50'],
            'semester'     => ['required', 'integer', 'between:1,8'],
        ];
    }
}
