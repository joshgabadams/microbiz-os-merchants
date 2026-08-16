<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AssignAgentComplaintRequest;
use App\Http\Requests\Agent\CreateAgentComplaintRequest;
use App\Http\Requests\Agent\ResolveAgentComplaintRequest;
use App\Models\AgentComplaint;
use App\Services\Payments\AgentComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AgentComplaintController extends Controller
{
    public function __construct(
        protected AgentComplaintService $complaintService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AgentComplaint::query()
            ->with([
                'agent',
                'branch',
                'location',
                'transaction',
                'assignedTo',
                'createdBy',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('category')) {
            $query->where(
                'category',
                $request->string('category')->toString()
            );
        }

        if ($request->filled('priority')) {
            $query->where(
                'priority',
                $request->string('priority')->toString()
            );
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->boolean('overdue')) {
            $query
                ->whereNotIn('status', ['RESOLVED', 'CLOSED'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now());
        }

        return response()->json(
            $query->paginate(
                min(
                    max($request->integer('per_page', 25), 1),
                    100
                )
            )
        );
    }

    public function store(
        CreateAgentComplaintRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['created_by'] = $request->user()?->id;

        $complaint = $this->complaintService->create($data);

        return response()->json(
            $complaint->load([
                'agent',
                'branch',
                'location',
                'transaction',
                'assignedTo',
                'createdBy',
            ]),
            201
        );
    }

    public function show(
        AgentComplaint $complaint
    ): JsonResponse {
        return response()->json(
            $complaint->load([
                'agent',
                'branch',
                'location',
                'transaction',
                'assignedTo',
                'createdBy',
            ])
        );
    }

    public function acknowledge(
    AgentComplaint $complaint
): JsonResponse {
    try {
        $complaint = $this->complaintService->acknowledge(
            $complaint
        );

        return response()->json($complaint);
    } catch (Exception $exception) {
        return $this->conflict($exception);
    }
}

public function assign(
    AssignAgentComplaintRequest $request,
    AgentComplaint $complaint
): JsonResponse {
    try {
        $complaint = $this->complaintService->assign(
            $complaint,
            (int) $request->validated('assigned_to')
        );

        return response()->json(
            $complaint->load('assignedTo')
        );
    } catch (Exception $exception) {
        return $this->conflict($exception);
    }
}

public function startProgress(
    AgentComplaint $complaint
): JsonResponse {
    try {
        $complaint = $this->complaintService->startProgress(
            $complaint
        );

        return response()->json($complaint);
    } catch (Exception $exception) {
        return $this->conflict($exception);
    }
}

public function escalate(
    Request $request,
    AgentComplaint $complaint
): JsonResponse {
    $validated = $request->validate([
        'reason' => [
            'required',
            'string',
            'max:5000',
        ],
    ]);

    try {
        $complaint = $this->complaintService->escalate(
            $complaint,
            $validated['reason']
        );

        return response()->json($complaint);
    } catch (Exception $exception) {
        return $this->conflict($exception);
    }
}

public function resolve(
    ResolveAgentComplaintRequest $request,
    AgentComplaint $complaint
): JsonResponse {
    try {
        $complaint = $this->complaintService->resolve(
            $complaint,
            $request->validated('resolution_summary')
        );

        return response()->json($complaint);
    } catch (Exception $exception) {
        return $this->conflict($exception);
    }
}

public function close(
    AgentComplaint $complaint
): JsonResponse {
    try {
        $complaint = $this->complaintService->close(
            $complaint
        );

        return response()->json($complaint);
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