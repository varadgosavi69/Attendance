<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'subject_id'           => ['required', 'integer', 'exists:subjects,subject_id'],
            'date'                 => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'records'              => ['required', 'array', 'min:1'],
            'records.*'            => ['required', 'string', 'in:Present,Absent,Leave'],
        ];
    }
}
