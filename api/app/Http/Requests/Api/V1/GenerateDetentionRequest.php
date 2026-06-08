<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'         => ['nullable', 'integer', 'between:2020,2100'],
            'month'        => ['nullable', 'integer', 'between:1,12'],
            'send_emails'  => ['nullable', 'boolean'],
        ];
    }
}
