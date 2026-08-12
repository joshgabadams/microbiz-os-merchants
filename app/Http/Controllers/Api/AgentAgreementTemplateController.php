<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\CreateAgentAgreementTemplateRequest;
use App\Services\Payments\AgentAgreementTemplateService;
use App\Traits\ApiResponse;

class AgentAgreementTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AgentAgreementTemplateService $templateService
    ) {
    }

    public function index()
    {
        return $this->success(
            $this->templateService->list(),
            'Agent agreement templates retrieved successfully.'
        );
    }

    public function store(CreateAgentAgreementTemplateRequest $request)
    {
        $template = $this->templateService->create($request->validated(), $request->user()->id);

        return $this->success($template, 'Agent agreement template created successfully.', 201);
    }
}
