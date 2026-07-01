<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sync\OfficeSyncService;

class OfficeSyncController extends Controller
{
    public function sync(
        OfficeSyncService $service
    )
    {
        $count = $service->sync();

        return response()->json([
            'message' => 'Office Sync Successful',
            'offices_synced' => $count
        ]);
    }
}
