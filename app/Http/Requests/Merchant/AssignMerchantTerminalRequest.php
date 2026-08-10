<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class AssignMerchantTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'merchant_location_id' => ['nullable', 'integer', 'exists:merchant_locations,id'],
            'terminal_id' => ['required', 'string', 'max:255', 'unique:merchant_terminals,terminal_id'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:merchant_terminals,serial_number'],
            'terminal_type' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'application_version' => ['nullable', 'string', 'max:255'],
        ];
    }
}
