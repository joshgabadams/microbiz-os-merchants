<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RecordAgreementApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:APPROVED,REJECTED'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
