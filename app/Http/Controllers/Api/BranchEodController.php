<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Branch\BranchEodService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class BranchEodController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BranchEodService $branchEodService
    ) {
    }

    public function close(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => ['required', 'integer'],
                'business_date' => ['required', 'date'],
                'note' => ['nullable', 'string'],
            ]);

            $result = $this->branchEodService->close(
                $validated['branch_id'],
                $validated['business_date'],
                $request->user()->id,
                $validated['note'] ?? null
            );

            return $this->success($result, 'Branch EOD closed successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
