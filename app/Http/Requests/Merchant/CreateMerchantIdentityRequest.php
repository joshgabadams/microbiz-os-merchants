<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Applicant-facing: creates a brand-new Fincore360 client for someone with
 * no existing account. Mirrors the Person/Entity split Fineract's own
 * client form uses (confirmed live against the real instance) -- each
 * branch requires different fields, not just optional extras.
 */
class CreateMerchantIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'legal_form' => ['required', 'string', 'in:Person,Entity'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            'first_name' => ['required_if:legal_form,Person', 'string', 'max:255'],
            'last_name' => ['required_if:legal_form,Person', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],

            'full_name' => ['required_if:legal_form,Entity', 'string', 'max:255'],
        ];
    }
}
