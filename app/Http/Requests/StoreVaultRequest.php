<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
         'branch_id' => 'required|exists:branches,id',

        'gl_account_id' => 'required|exists:gl_accounts,id',

        'name' => 'required|string|max:255',

        'code' => 'required|string|unique:vaults,code',

        'type' => 'sometimes|in:HEAD_OFFICE,MAIN,RESERVE,ATM,CASH_IN_TRANSIT,TREASURY',

        'currency' => 'sometimes|string|size:3',

        'minimum_balance' => 'sometimes|numeric',

        'maximum_balance' => 'sometimes|numeric',

        'active' => 'boolean'
        ];
    }
}
