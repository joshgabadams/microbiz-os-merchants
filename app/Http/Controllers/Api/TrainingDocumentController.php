<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\CreateTrainingDocumentRequest;
use App\Services\Payments\TrainingDocumentService;
use App\Traits\ApiResponse;

class TrainingDocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TrainingDocumentService $trainingDocumentService
    ) {
    }

    public function index()
    {
        return $this->success(
            $this->trainingDocumentService->list(),
            'Training documents retrieved successfully.'
        );
    }

    public function store(CreateTrainingDocumentRequest $request)
    {
        $document = $this->trainingDocumentService->create($request->validated(), $request->user()->id);

        return $this->success($document, 'Training document created successfully.', 201);
    }
}
