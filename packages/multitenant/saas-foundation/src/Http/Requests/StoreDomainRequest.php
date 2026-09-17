<?php

namespace SaasFoundation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9\-\.]+\.[a-z]{2,}$/i'],
            'type' => ['required', 'in:subdomain,custom'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
