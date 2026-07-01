<?php

namespace App\Services\Sync;

use App\Models\Branch;
use App\Services\Fineract\FineractClient;

class OfficeSyncService
{
    public function sync()
    {
        $offices = app(FineractClient::class)
            ->getOffices()['body'];

        $count = 0;

        foreach ($offices as $office) {

            Branch::updateOrCreate(

                [
                    'office_id' => $office['id']
                ],

                [
                    'name' => $office['name'],
                    'code' => 'OFF'.$office['id']
                ]

            );

            $count++;
        }

        return $count;
    }
}