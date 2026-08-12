<?php

namespace App\Services\Payments;

use App\Models\TrainingDocument;

class TrainingDocumentService
{
    public function list()
    {
        return TrainingDocument::orderByDesc('created_at')->get();
    }

    public function create(array $data, int $createdBy): TrainingDocument
    {
        return TrainingDocument::create([
            ...$data,
            'status' => 'ACTIVE',
            'created_by' => $createdBy,
        ]);
    }
}
