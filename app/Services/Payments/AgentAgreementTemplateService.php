<?php

namespace App\Services\Payments;

use App\Models\AgentAgreementTemplate;

class AgentAgreementTemplateService
{
    public function list()
    {
        return AgentAgreementTemplate::orderByDesc('created_at')->get();
    }

    public function create(array $data, int $createdBy): AgentAgreementTemplate
    {
        return AgentAgreementTemplate::create([
            ...$data,
            'status' => 'DRAFT',
            'created_by' => $createdBy,
        ]);
    }
}
