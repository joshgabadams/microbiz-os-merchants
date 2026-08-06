<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by every Agent lifecycle action that requires a reason
 * (reject/restrict/suspend/terminate) -- identical validation, no point
 * in four near-duplicate request classes.
 */
class AgentReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
