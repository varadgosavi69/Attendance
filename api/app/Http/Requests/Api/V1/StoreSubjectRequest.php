<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_name' => ['required', 'string', 'max:100'],
            'subject_code' => ['required', 'string', 'max:20', 'unique:subjects,subject_code'],
            'department'   => ['required', 'string', 'max:50'],
            'semester'     => ['required', 'integer', 'between:1,8'],
        ];
    }
}
