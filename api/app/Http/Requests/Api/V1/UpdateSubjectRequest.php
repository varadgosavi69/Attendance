<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject');

        return [
            'subject_name' => ['sometimes', 'required', 'string', 'max:100'],
            'subject_code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('subjects', 'subject_code')->ignore($subjectId, 'subject_id')],
            'department'   => ['sometimes', 'required', 'string', 'max:50'],
            'semester'     => ['sometimes', 'required', 'integer', 'between:1,8'],
        ];
    }
}
