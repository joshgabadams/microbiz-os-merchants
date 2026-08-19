<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AddAgentDocumentRequest;
use App\Http\Requests\Agent\AddAgentOwnerRequest;
use App\Http\Requests\Agent\AgentReasonRequest;
use App\Http\Requests\Agent\OnboardAgentRequest;
use App\Models\Agent;
use App\Services\Payments\AgentActivationService;
use App\Services\Payments\AgentApprovalService;
use App\Services\Payments\AgentKycService;
use App\Services\Payments\AgentRegistrationService;
use App\Traits\ApiResponse;
use App\Http\Requests\Agent\CreateAgentAgreementRequest;
use App\Http\Requests\Agent\CreateAgentLocationRequest;
use App\Http\Requests\Agent\CreateAgentOperatorRequest;
use App\Http\Requests\Agent\CreateAgentTerminalRequest;
use App\Http\Requests\Agent\AssignAgentTerminalLocationRequest;
use App\Http\Requests\Agent\AgentTerminalHeartbeatRequest;
use App\Http\Requests\Agent\AgentTerminalLocationCheckRequest;
use App\Models\AgentAgreement;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTrainingRecord;
use App\Models\TrainingDocument;
use App\Services\Payments\AgentAgreementService;
use App\Services\Payments\AgentLocationService;
use App\Services\Payments\AgentOperatorService;
use App\Services\Payments\AgentTerminalService;
use App\Services\Payments\AgentTrainingService;
use App\Http\Requests\Agent\RecordTrainingDownloadRequest;
use Exception;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AgentRegistrationService $registrationService,
        protected AgentApprovalService $approvalService,
        protected AgentActivationService $activationService,
        protected AgentKycService $kycService,
        protected AgentLocationService $locationService,
        protected AgentAgreementService $agreementService,
        protected AgentOperatorService $operatorService,
        protected AgentTerminalService $terminalService,
        protected AgentTrainingService $trainingService

    ) {
    }

    public function index()
    {
        return $this->success(
            Agent::with(['branch', 'supervisor'])->get(),
            'Agents retrieved successfully.'
        );
    }

    public function show(Agent $agent)
    {
        return $this->success(
            $agent->load([
                'branch',
                'supervisor',
                'trainingRecords.trainingDocument',
                'owners',
                'documents',
                'locations' => fn ($query) => $query->orderByDesc('created_at'),
                'agreements' => fn ($query) => $query->with(['approvals', 'signatories', 'template'])->orderByDesc('version'),
                'operators',
                'terminals',
                'transactions' => fn ($query) => $query->with(['terminal', 'operator'])->orderByDesc('created_at'),
            ]),
            'Agent retrieved successfully.'
        );
    }

    public function store(OnboardAgentRequest $request)
    {
        try {
            $agent = $this->registrationService->register(
                $request->validated(),
                $request->user()->id
            );

            return $this->success($agent, 'Agent registered successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request, Agent $agent)
    {
        $agent->update($request->only([
            'trading_name', 'registration_number', 'tax_identification_number', 'bvn',
            'phone', 'email', 'supervisor_id', 'principal_reference',
            'daily_transaction_limit', 'daily_cash_out_limit', 'single_transaction_limit',
            'next_review_date',
        ]));

        return $this->success($agent->fresh(), 'Agent updated successfully.');
    }

    public function submit(Agent $agent, Request $request)
    {
        try {
            $result = $this->approvalService->submit($agent, $request->user()->id);

            return $this->success($result, 'Agent submitted for approval.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function approve(Agent $agent, Request $request)
    {
        try {
            $result = $this->approvalService->approve($agent, $request->user()->id);

            return $this->success($result, 'Agent approved.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reject(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->approvalService->reject($agent, $request->user()->id, $request->reason);

            return $this->success($result, 'Agent rejected.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function activate(Agent $agent)
    {
        try {
            $result = $this->activationService->activate($agent);

            return $this->success($result, 'Agent activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function restrict(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->restrict($agent, $request->reason);

            return $this->success($result, 'Agent restricted.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspend(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->suspend($agent, $request->reason);

            return $this->success($result, 'Agent suspended.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reactivate(Agent $agent)
    {
        try {
            $result = $this->activationService->reactivate($agent);

            return $this->success($result, 'Agent reactivated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function terminate(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->terminate($agent, $request->reason);

            return $this->success($result, 'Agent terminated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ---------- AG-02: KYC & Approval ----------

    public function listOwners(Agent $agent)
    {
        return $this->success($agent->owners, 'Agent owners retrieved successfully.');
    }

    public function addOwner(Agent $agent, AddAgentOwnerRequest $request)
    {
        $owner = $agent->owners()->create($request->validated());

        return $this->success($owner, 'Agent owner added successfully.', 201);
    }

    public function listDocuments(Agent $agent)
    {
        return $this->success($agent->documents, 'Agent documents retrieved successfully.');
    }

    public function addDocument(Agent $agent, AddAgentDocumentRequest $request)
    {
        $document = $agent->documents()->create($request->validated());

        return $this->success($document, 'Agent document added successfully.', 201);
    }

    public function completeKyc(Agent $agent, Request $request)
    {
        try {
            $result = $this->kycService->completeKyc($agent, $request->user()->id);

            return $this->success($result, 'Agent KYC completed; moved to location verification.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ---------- AG-03: Locations & Agreements ----------

    public function listLocations(Agent $agent)
    {
        return $this->success(
            $agent->locations()
                ->orderByDesc('created_at')
                ->get(),
            'Agent locations retrieved successfully.'
        );
    }

    public function createLocation(
        Agent $agent,
        CreateAgentLocationRequest $request
    ) {
        try {
            $location = $this->locationService->createLocation(
                $agent,
                $request->validated(),
                $request->user()->id
            );

            return $this->success(
                $location,
                'Agent location registered successfully.',
                201
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function verifyLocation(
        Agent $agent,
        AgentLocation $location,
        Request $request
    ) {
        try {
            $result = $this->locationService->verifyLocation(
                $agent,
                $location,
                $request->user()->id,
                $request->input('notes')
            );

            return $this->success(
                $result,
                'Agent location verified; moved to compliance review.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

public function rejectLocation(
    Agent $agent,
    AgentLocation $location,
    AgentReasonRequest $request
) {
    try {
        $location = $this->locationService->rejectLocation(
            $agent,
            $location,
            $request->user()->id,
            $request->reason
        );

        return $this->success(
            $location,
            'Agent location rejected.'
        );
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}


public function completeComplianceReview(
    Agent $agent,
    Request $request
) {
    try {
        $result = $this->approvalService
            ->completeComplianceReview(
                $agent,
                $request->user()->id
            );

        return $this->success(
            $result,
            'Agent compliance review completed; moved to approval.'
        );
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

public function listAgreements(Agent $agent)
{
    return $this->success(
        $agent->agreements()
            ->with(['approvals', 'signatories', 'template'])
            ->orderByDesc('version')
            ->get(),
        'Agent agreements retrieved successfully.'
    );
}

public function createAgreement(
    Agent $agent,
    CreateAgentAgreementRequest $request
) {
    try {
        $template = \App\Models\AgentAgreementTemplate::findOrFail($request->agreement_template_id);

        $agreement = $this->agreementService->createAgreement(
            $agent,
            $template,
            $request->validated(),
            $request->user()->id
        );

        return $this->success(
            $agreement,
            'Agent agreement drafted successfully.',
            201
        );
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

public function submitAgreementForReview(Agent $agent, AgentAgreement $agreement, Request $request)
{
    try {
        $result = $this->agreementService->submitForReview($agreement, $request->user()->id);

        return $this->success($result, 'Agent agreement submitted for internal review.');
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

private function recordApprovalDecision(
    Agent $agent,
    AgentAgreement $agreement,
    string $approvalType,
    \App\Http\Requests\Agent\RecordAgreementApprovalRequest $request
) {
    try {
        $result = $this->agreementService->recordApproval(
            $agreement,
            $approvalType,
            $request->user()->id,
            $request->decision,
            $request->notes
        );

        return $this->success($result, "{$approvalType} decision recorded.");
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

public function approveAgreementRisk(Agent $agent, AgentAgreement $agreement, \App\Http\Requests\Agent\RecordAgreementApprovalRequest $request)
{
    return $this->recordApprovalDecision($agent, $agreement, 'RISK', $request);
}

public function approveAgreementCompliance(Agent $agent, AgentAgreement $agreement, \App\Http\Requests\Agent\RecordAgreementApprovalRequest $request)
{
    return $this->recordApprovalDecision($agent, $agreement, 'COMPLIANCE', $request);
}

public function approveAgreementLegal(Agent $agent, AgentAgreement $agreement, \App\Http\Requests\Agent\RecordAgreementApprovalRequest $request)
{
    return $this->recordApprovalDecision($agent, $agreement, 'LEGAL', $request);
}

public function approveAgreementBusinessOwner(Agent $agent, AgentAgreement $agreement, \App\Http\Requests\Agent\RecordAgreementApprovalRequest $request)
{
    return $this->recordApprovalDecision($agent, $agreement, 'BUSINESS_OWNER', $request);
}

public function sendAgreementForSignature(Agent $agent, AgentAgreement $agreement)
{
    try {
        $result = $this->agreementService->sendForSignature($agreement);

        return $this->success($result, 'Agent agreement sent for signature.');
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

public function recordAgreementSignature(
    Agent $agent,
    AgentAgreement $agreement,
    \App\Http\Requests\Agent\RecordAgreementSignatureRequest $request
) {
    try {
        $result = $this->agreementService->recordSignature(
            $agreement,
            $request->validated(),
            $request->user()->id,
            $request->ip()
        );

        return $this->success($result, 'Signature recorded.', 201);
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

public function uploadAgreementSignatureEvidence(
    Agent $agent,
    AgentAgreement $agreement,
    \App\Http\Requests\Agent\UploadAgreementSignatureEvidenceRequest $request
) {
    try {
        $path = $this->agreementService->uploadSignatureEvidence(
            $agreement,
            $request->file('evidence')
        );

        return $this->success(['path' => $path], 'Evidence uploaded.', 201);
    } catch (Exception $e) {
        return $this->error($e->getMessage());
    }
}

 public function executeAgreement(
        Agent $agent,
        AgentAgreement $agreement,
        Request $request
    ) {
        try {
            $result = $this->agreementService->executeAgreement(
                $agent,
                $agreement,
                $request->user()->id
            );

            return $this->success(
                $result,
                'Agent agreement executed; moved to training.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ---------- AG-04: Operators ----------

    public function listOperators(Agent $agent)
    {
        return $this->success(
            $this->operatorService->list($agent),
            'Agent operators retrieved successfully.'
        );
    }

    public function createOperator(Agent $agent, CreateAgentOperatorRequest $request)
    {
        try {
            $operator = $this->operatorService->create($agent, $request->validated());

            return $this->success($operator, 'Agent operator assigned successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function activateOperator(AgentOperator $operator)
    {
        try {
            $result = $this->operatorService->activate($operator);

            return $this->success($result, 'Agent operator activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspendOperator(AgentOperator $operator)
    {
        try {
            $result = $this->operatorService->suspend($operator);

            return $this->success($result, 'Agent operator suspended.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ---------- AG-04: Terminals & Geo-Fence ----------

    public function listTerminals(Agent $agent)
    {
        return $this->success(
            $this->terminalService->list($agent),
            'Agent terminals retrieved successfully.'
        );
    }

    public function createTerminal(CreateAgentTerminalRequest $request)
    {
        try {
            $agent = Agent::findOrFail($request->agent_id);

            $terminal = $this->terminalService->create(
                $agent,
                $request->validated(),
                $request->user()->id
            );

            return $this->success($terminal, 'Agent terminal registered successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function assignTerminalLocation(AgentTerminal $terminal, AssignAgentTerminalLocationRequest $request)
    {
        try {
            $result = $this->terminalService->assignLocation($terminal, $request->agent_location_id);

            return $this->success($result, 'Agent terminal relocated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function activateTerminal(AgentTerminal $terminal)
    {
        try {
            $result = $this->terminalService->activate($terminal);

            return $this->success($result, 'Agent terminal activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspendTerminal(AgentTerminal $terminal)
    {
        try {
            $result = $this->terminalService->suspend($terminal);

            return $this->success($result, 'Agent terminal suspended.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function terminalHeartbeat(AgentTerminal $terminal, AgentTerminalHeartbeatRequest $request)
    {
        $result = $this->terminalService->heartbeat($terminal, $request->validated(), $request->ip());

        return $this->success($result, 'Heartbeat recorded.');
    }

    public function terminalLocationCheck(AgentTerminal $terminal, AgentTerminalLocationCheckRequest $request)
    {
        $result = $this->terminalService->checkLocation(
            $terminal,
            (float) $request->latitude,
            (float) $request->longitude,
            $request->ip()
        );

        return $this->success($result, 'Location check completed.');
    }

    // ---------- Training ----------

    public function recordTrainingDownload(Agent $agent, RecordTrainingDownloadRequest $request)
    {
        try {
            $document = TrainingDocument::findOrFail($request->training_document_id);

            $record = $this->trainingService->recordDownload(
                $agent,
                $document,
                $request->operator_id,
                $request->user()->id,
                $request->ip()
            );

            return $this->success($record, 'Training guide download recorded.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function acknowledgeTraining(Agent $agent, AgentTrainingRecord $trainingRecord, Request $request)
    {
        try {
            $result = $this->trainingService->acknowledgeTrainingGuide($trainingRecord, $request->user()->id);

            return $this->success($result, 'Training guide acknowledged.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
