<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * RBAC for the Payments domain (M-PAY). Kept as its own seeder, separate
 * from RbacSeeder, so each domain owns its own permissions/roles file
 * rather than one growing core seeder -- the same "domain owns its own
 * migrations/seeders" convention app/Domain/Finance already implies.
 */
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
            // Agent registry permissions (Blueprint §12) -- only the
            // AG-01-relevant subset; kyc-review/locations/agreements/etc.
            // belong to later sprints (AG-02/AG-03/AG-04).
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
                'permissions' => ['agents.view', 'agents.create', 'agents.edit', 'agents.submit'],
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
