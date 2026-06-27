<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = $this->user()?->organization_id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'assignee_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'tags' => ['sometimes', 'array'],
        ];
    }
}
