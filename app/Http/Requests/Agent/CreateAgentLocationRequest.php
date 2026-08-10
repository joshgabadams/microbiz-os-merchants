<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgentLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'local_government' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'approved_radius_metres' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],

            'verification_evidence' => [
                'nullable',
                'array',
            ],
        ];
    }
}
