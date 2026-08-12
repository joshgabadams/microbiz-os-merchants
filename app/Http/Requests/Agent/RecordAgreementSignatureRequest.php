<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RecordAgreementSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party' => ['required', 'string', 'in:MICROBIZ,AGENT'],
            'signatory_name' => ['required', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'signature_method' => ['required', 'string', 'in:WET_SIGNATURE_UPLOAD,E_SIGNATURE'],
            'signature_evidence_path' => ['required', 'string', 'max:500'],
            'provider_reference_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
