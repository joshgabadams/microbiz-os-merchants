<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PaymentsRbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'wallets.manage' => 'payments',
            'merchants.onboard' => 'payments',
            'merchants.edit' => 'payments',
            'merchants.submit' => 'payments',
            'merchants.approve' => 'payments',
            'merchants.reject' => 'payments',
            'merchants.activate' => 'payments',
            'merchants.suspend' => 'payments',
            'merchants.reactivate' => 'payments',
            'merchants.deactivate' => 'payments',
            'merchants.settle' => 'payments',
            'merchants.owners.manage' => 'payments',
            'merchants.documents.manage' => 'payments',
            'merchants.locations.manage' => 'payments',
            'payments.process' => 'payments',
            'agency_banking.manage' => 'payments',
            'agents.view' => 'payments',
            'agents.create' => 'payments',
            'agents.edit' => 'payments',
            'agents.submit' => 'payments',
            'agents.approve' => 'payments',
            'agents.reject' => 'payments',
            'agents.activate' => 'payments',
            'agents.restrict' => 'payments',
            'agents.suspend' => 'payments',
            'agents.reactivate' => 'payments',
            'agents.terminate' => 'payments',
            'agents.owners.manage' => 'payments',
            'agents.documents.manage' => 'payments',
            'agents.kyc.review' => 'payments',
            'agents.locations.create' => 'payments',
            'agents.locations.verify' => 'payments',
            'agents.compliance.review' => 'payments',
            'agents.agreements.create' => 'payments',
            'agents.agreements.execute' => 'payments',
        ];

        foreach ($permissions as $name => $module) {
            Permission::firstOrCreate(['name' => $name], ['module' => $module]);
        }

        $roles = [
            'payments-officer' => [
                'label' => 'Payments Officer',
                'permissions' => ['wallets.manage', 'payments.process'],
            ],
            'merchant-support' => [
                'label' => 'Merchant Support',
                'permissions' => [
                    'merchants.onboard', 'merchants.edit', 'merchants.submit',
                    'merchants.owners.manage', 'merchants.documents.manage', 'merchants.locations.manage',
                    'payments.process',
                ],
            ],
            'merchant-approval-officer' => [
                'label' => 'Merchant Approval Officer',
                'permissions' => [
                    'merchants.approve', 'merchants.reject', 'merchants.activate',
                    'merchants.suspend', 'merchants.reactivate', 'merchants.deactivate',
                ],
            ],
            'merchant-settlement-officer' => [
                'label' => 'Merchant Settlement Officer',
                'permissions' => ['merchants.settle'],
            ],
            'agency-banking-agent' => [
                'label' => 'Agency Banking Agent',
                'permissions' => ['agency_banking.manage', 'payments.process'],
            ],
            'agent-registration-officer' => [
                'label' => 'Agent Registration Officer',
                'permissions' => [
                    'agents.view', 'agents.create', 'agents.edit', 'agents.submit',
                    'agents.owners.manage', 'agents.documents.manage',
                ],
            ],
            'agent-kyc-officer' => [
                'label' => 'Agent KYC Officer',
                'permissions' => ['agents.view', 'agents.kyc.review'],
            ],
            'agent-location-officer' => [
    'label' => 'Agent Location Officer',
    'permissions' => [
        'agents.view',
        'agents.locations.create',
    ],
],

'agent-location-verifier' => [
    'label' => 'Agent Location Verifier',
    'permissions' => [
        'agents.view',
        'agents.locations.verify',
    ],
],

'agent-compliance-officer' => [
    'label' => 'Agent Compliance Officer',
    'permissions' => [
        'agents.view',
        'agents.compliance.review',
    ],
],

'agent-agreement-officer' => [
    'label' => 'Agent Agreement Officer',
    'permissions' => [
        'agents.view',
        'agents.agreements.create',
    ],
],

'agent-agreement-executor' => [
    'label' => 'Agent Agreement Executor',
    'permissions' => [
        'agents.view',
        'agents.agreements.execute',
    ],
],
            'agent-approval-officer' => [
                'label' => 'Agent Approval Officer',
                'permissions' => [
                    'agents.view', 'agents.approve', 'agents.reject', 'agents.activate',
                    'agents.restrict', 'agents.suspend', 'agents.reactivate', 'agents.terminate',
                ],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $config['label']],
            );

            $permissionIds = Permission::whereIn('name', $config['permissions'])->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->command->info('Payments domain permissions and roles seeded.');
    }
}
