<?php

namespace App\Services\Sync;

use App\Models\GlAccount;
use App\Services\Fineract\FineractClient;

class GlAccountSyncService
{
    public function sync()
    {
        $glAccounts = app(FineractClient::class)
            ->getGlAccounts()['body'];

        $count = 0;

        foreach ($glAccounts as $gl) {

            GlAccount::updateOrCreate(
                [
                    'fineract_gl_id' => $gl['id']
                ],
                [
                    'name' => $gl['name'],
                    'gl_code' => $gl['glCode'],
                    'type' => $gl['type']['value'],
                    'usage' => $gl['usage']['value'],
                    'manual_entries_allowed' => $gl['manualEntriesAllowed'],
                    'disabled' => $gl['disabled']
                ]
            );

            $count++;
        }

        return $count;
    }
}