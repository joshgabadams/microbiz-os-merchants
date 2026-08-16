<?php

namespace App\Services\Payments;

use App\Models\TrainingDocument;
use Illuminate\Http\UploadedFile;

class TrainingDocumentService
{
    public function list()
    {
        return TrainingDocument::orderByDesc('created_at')->get();
    }

    public function create(array $data, UploadedFile $file, int $createdBy): TrainingDocument
    {
        $path = $file->store('training-documents', 'local');

        return TrainingDocument::create([
            'name' => $data['name'],
            'version' => $data['version'],
            'file_path' => $path,
            'status' => 'ACTIVE',
            'created_by' => $createdBy,
        ]);
    }
}
