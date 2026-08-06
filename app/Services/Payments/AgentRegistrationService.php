<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentRegistrationService
{
    public function register(array $data, int $createdBy): Agent
    {
        return DB::transaction(function () use ($data, $createdBy) {
            return Agent::create([
                'agent_code' => $this->generateAgentCode(),
                'agent_type' => $data['agent_type'],
                'legal_name' => $data['legal_name'],
                'trading_name' => $data['trading_name'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'tax_identification_number' => $data['tax_identification_number'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'branch_id' => $data['branch_id'],
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'principal_reference' => $data['principal_reference'] ?? null,
                'daily_transaction_limit' => $data['daily_transaction_limit'] ?? null,
                'daily_cash_out_limit' => $data['daily_cash_out_limit'] ?? null,
                'single_transaction_limit' => $data['single_transaction_limit'] ?? null,
                'next_review_date' => $data['next_review_date'] ?? null,
                'status' => AgentStatus::DRAFT->value,
                'kyc_status' => 'PENDING',
                'risk_rating' => 'MEDIUM',
                'exclusive_relationship' => $data['exclusive_relationship'] ?? true,
                'created_by' => $createdBy,
            ]);
        });
    }

    protected function generateAgentCode(): string
    {
        do {
            $code = 'AGT-'.strtoupper(Str::random(8));
        } while (Agent::withTrashed()->where('agent_code', $code)->exists());

        return $code;
    }
}
