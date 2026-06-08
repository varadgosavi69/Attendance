<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class HodSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department'      => ['required', 'string', 'max:50'],
            'semester'        => ['required', 'integer', 'between:1,8'],
            'year'            => ['required', 'integer', 'between:2020,2100'],
            'date'            => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'total_students'  => ['required', 'integer', 'min:1'],
            'present_count'   => ['required', 'integer', 'min:0', 'lte:total_students'],
        ];
    }
}
