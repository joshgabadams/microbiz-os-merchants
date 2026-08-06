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
            'merchants.submit' => 'payments',
            'merchants.approve' => 'payments',
            'merchants.reject' => 'payments',
            'merchants.activate' => 'payments',
            'merchants.settle' => 'payments',
            'payments.process' => 'payments',
            'agency_banking.manage' => 'payments',
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
                'permissions' => ['merchants.onboard', 'merchants.submit', 'payments.process'],
            ],
            'merchant-approval-officer' => [
                'label' => 'Merchant Approval Officer',
                'permissions' => ['merchants.approve', 'merchants.reject', 'merchants.activate'],
            ],
            'merchant-settlement-officer' => [
                'label' => 'Merchant Settlement Officer',
                'permissions' => ['merchants.settle'],
            ],
            'agency-banking-agent' => [
                'label' => 'Agency Banking Agent',
                'permissions' => ['agency_banking.manage', 'payments.process'],
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
