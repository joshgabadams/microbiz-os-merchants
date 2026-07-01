<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sync\GlAccountSyncService;

class GlAccountSyncController extends Controller
{
    public function sync(
        GlAccountSyncService $service
    )
    {
        $count = $service->sync();

        return response()->json([
            'message' => 'GL Sync Successful',
            'gl_accounts_synced' => $count
        ]);
    }
}