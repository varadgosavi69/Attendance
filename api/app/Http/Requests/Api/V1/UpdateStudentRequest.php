<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student');

        return [
            'roll_number'  => ['sometimes', 'required', 'string', 'max:20', Rule::unique('students', 'roll_number')->ignore($studentId, 'student_id')],
            'student_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email'        => ['sometimes', 'required', 'email', 'max:100', Rule::unique('students', 'email')->ignore($studentId, 'student_id')],
            'parent_email' => ['nullable', 'email', 'max:100'],
            'department'   => ['sometimes', 'required', 'string', 'max:50'],
            'semester'     => ['sometimes', 'required', 'integer', 'between:1,8'],
        ];
    }
}
