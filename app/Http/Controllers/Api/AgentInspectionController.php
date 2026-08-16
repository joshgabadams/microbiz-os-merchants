<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\CompleteAgentInspectionRequest;
use App\Http\Requests\Agent\CreateAgentInspectionRequest;
use App\Models\AgentInspection;
use App\Services\Payments\AgentInspectionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentInspectionController extends Controller
{
    public function __construct(
        protected AgentInspectionService $inspectionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AgentInspection::query()
            ->with(['agent', 'location', 'inspector', 'createdBy'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('compliance_outcome')) {
            $query->where(
                'compliance_outcome',
                $request->string('compliance_outcome')->toString()
            );
        }

        if ($request->filled('follow_up_status')) {
            $query->where(
                'follow_up_status',
                $request->string('follow_up_status')->toString()
            );
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->boolean('overdue')) {
            $query
                ->where('follow_up_status', 'PENDING')
                ->whereNotNull('corrective_action_deadline')
                ->where('corrective_action_deadline', '<', now());
        }

        return response()->json(
            $query->paginate(
                min(max($request->integer('per_page', 25), 1), 100)
            )
        );
    }

    public function store(
        CreateAgentInspectionRequest $request
    ): JsonResponse {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $inspection = $this->inspectionService->create($data);

        return response()->json(
            $inspection->load(['agent', 'location', 'inspector', 'createdBy']),
            201
        );
    }

    public function show(AgentInspection $inspection): JsonResponse
    {
        return response()->json(
            $inspection->load(['agent', 'location', 'inspector', 'createdBy'])
        );
    }

    public function start(AgentInspection $inspection): JsonResponse
    {
        try {
            return response()->json(
                $this->inspectionService->start($inspection)
            );
        } catch (Exception $exception) {
            return $this->conflict($exception);
        }
    }

    public function complete(
        CompleteAgentInspectionRequest $request,
        AgentInspection $inspection
    ): JsonResponse {
        try {
            return response()->json(
                $this->inspectionService->complete(
                    $inspection,
                    $request->validated()
                )
            );
        } catch (Exception $exception) {
            return $this->conflict($exception);
        }
    }

    public function cancel(
        Request $request,
        AgentInspection $inspection
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        try {
            return response()->json(
                $this->inspectionService->cancel(
                    $inspection,
                    $validated['reason']
                )
            );
        } catch (Exception $exception) {
            return $this->conflict($exception);
        }
    }

    public function startFollowUp(AgentInspection $inspection): JsonResponse
    {
        try {
            return response()->json(
                $this->inspectionService->startFollowUp($inspection)
            );
        } catch (Exception $exception) {
            return $this->conflict($exception);
        }
    }

    public function completeFollowUp(
        Request $request,
        AgentInspection $inspection
    ): JsonResponse {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            return response()->json(
                $this->inspectionService->completeFollowUp(
                    $inspection,
                    $validated['notes'] ?? null
                )
            );
        } catch (Exception $exception) {
            return $this->conflict($exception);
        }
    }

    protected function conflict(Exception $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], 409);
    }
}
