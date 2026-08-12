<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RecordTrainingDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'training_document_id' => ['required', 'integer', 'exists:training_documents,id'],
            'operator_id' => ['nullable', 'integer', 'exists:agent_operators,id'],
        ];
    }
}
