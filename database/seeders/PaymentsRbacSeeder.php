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

            // Merchant lifecycle
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

            // Merchant terminals
            'merchant-terminals.view' => 'payments',
            'merchant-terminals.assign' => 'payments',
            'merchant-terminals.activate' => 'payments',
            'merchant-terminals.suspend' => 'payments',

            // Generic payments / agency banking
            'payments.process' => 'payments',
            'agency_banking.manage' => 'payments',

            // Agent lifecycle
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

            // Agent KYC
            'agents.owners.manage' => 'payments',
            'agents.documents.manage' => 'payments',
            'agents.kyc.review' => 'payments',

            // Agent locations / compliance
            'agents.locations.create' => 'payments',
            'agents.locations.verify' => 'payments',
            'agents.compliance.review' => 'payments',

            // Agent agreements
            'agents.agreements.create' => 'payments',
            'agents.agreements.execute' => 'payments',
            'agents.agreements.submit-review' => 'payments',
            'agents.agreements.approve-risk' => 'payments',
            'agents.agreements.approve-compliance' => 'payments',
            'agents.agreements.approve-legal' => 'payments',
            'agents.agreements.approve-business-owner' => 'payments',
            'agents.agreements.send-for-signature' => 'payments',
            'agents.agreements.sign' => 'payments',
            'agents.agreements.templates.manage' => 'payments',

            // Agent training
            'agents.training.documents.manage' => 'payments',
            'agents.training.record' => 'payments',

            // Agent operators
            'agents.operators.view' => 'payments',
            'agents.operators.manage' => 'payments',

            // Agent terminals
            'agents.terminals.view' => 'payments',
            'agents.terminals.assign' => 'payments',
            'agents.terminals.activate' => 'payments',
            'agents.terminals.suspend' => 'payments',

            // Agent operational transactions
            'agents.transactions.view' => 'payments',
            'agents.transactions.cash-in' => 'payments',
            'agents.transactions.cash-out' => 'payments',
            'agents.transactions.transfer' => 'payments',
            'agents.transactions.reverse' => 'payments',
        ];

        foreach ($permissions as $name => $module) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['module' => $module]
            );
        }

        $roles = [
            'payments-officer' => [
                'label' => 'Payments Officer',
                'permissions' => [
                    'wallets.manage',
                    'payments.process',
                ],
            ],

            'merchant-support' => [
                'label' => 'Merchant Support',
                'permissions' => [
                    'merchants.onboard',
                    'merchants.edit',
                    'merchants.submit',
                    'merchants.owners.manage',
                    'merchants.documents.manage',
                    'merchants.locations.manage',
                    'payments.process',
                ],
            ],

            'merchant-approval-officer' => [
                'label' => 'Merchant Approval Officer',
                'permissions' => [
                    'merchants.approve',
                    'merchants.reject',
                    'merchants.activate',
                    'merchants.suspend',
                    'merchants.reactivate',
                    'merchants.deactivate',
                ],
            ],

            'merchant-settlement-officer' => [
                'label' => 'Merchant Settlement Officer',
                'permissions' => [
                    'merchants.settle',
                ],
            ],

            'merchant-terminal-officer' => [
                'label' => 'Merchant Terminal Officer',
                'permissions' => [
                    'merchant-terminals.view',
                    'merchant-terminals.assign',
                    'merchant-terminals.activate',
                    'merchant-terminals.suspend',
                ],
            ],

            'agency-banking-agent' => [
                'label' => 'Agency Banking Agent',
                'permissions' => [
                    'agency_banking.manage',
                    'payments.process',
                ],
            ],

            'agent-registration-officer' => [
                'label' => 'Agent Registration Officer',
                'permissions' => [
                    'agents.view',
                    'agents.create',
                    'agents.edit',
                    'agents.submit',
                    'agents.owners.manage',
                    'agents.documents.manage',
                ],
            ],

            'agent-kyc-officer' => [
                'label' => 'Agent KYC Officer',
                'permissions' => [
                    'agents.view',
                    'agents.kyc.review',
                ],
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
                    'agents.agreements.submit-review',
                    'agents.agreements.send-for-signature',
                    'agents.agreements.sign',
                ],
            ],

            'agent-agreement-risk-approver' => [
                'label' => 'Agent Agreement Risk Approver',
                'permissions' => [
                    'agents.view',
                    'agents.agreements.approve-risk',
                ],
            ],

            'agent-agreement-compliance-approver' => [
                'label' => 'Agent Agreement Compliance Approver',
                'permissions' => [
                    'agents.view',
                    'agents.agreements.approve-compliance',
                ],
            ],

            'agent-agreement-legal-approver' => [
                'label' => 'Agent Agreement Legal Approver',
                'permissions' => [
                    'agents.view',
                    'agents.agreements.approve-legal',
                ],
            ],

            'agent-agreement-business-owner-approver' => [
                'label' => 'Agent Agreement Business Owner Approver',
                'permissions' => [
                    'agents.view',
                    'agents.agreements.approve-business-owner',
                ],
            ],

            'agent-training-officer' => [
                'label' => 'Agent Training Officer',
                'permissions' => [
                    'agents.view',
                    'agents.training.documents.manage',
                    'agents.training.record',
                ],
            ],

            'agent-operator-officer' => [
                'label' => 'Agent Operator Officer',
                'permissions' => [
                    'agents.view',
                    'agents.operators.view',
                    'agents.operators.manage',
                ],
            ],

            'agent-terminal-officer' => [
                'label' => 'Agent Terminal Officer',
                'permissions' => [
                    'agents.view',
                    'agents.terminals.view',
                    'agents.terminals.assign',
                    'agents.terminals.activate',
                    'agents.terminals.suspend',
                ],
            ],

            /*
             * Dedicated operational transaction role.
             *
             * Keep day-to-day money movement separate from sensitive
             * reversal initiation and approval authority.
             */
            'agent-transaction-operator' => [
                'label' => 'Agent Transaction Operator',
                'permissions' => [
                    'agents.view',
                    'agents.transactions.view',
                    'agents.transactions.cash-in',
                    'agents.transactions.cash-out',
                    'agents.transactions.transfer',
                ],
            ],

            /*
             * Dedicated reversal maker role.
             *
             * This role may request an agent transaction reversal but
             * deliberately cannot approve the resulting approval request.
             * Approval remains protected separately by approvals.approve.
             */
            'agent-transaction-reversal-maker' => [
                'label' => 'Agent Transaction Reversal Maker',
                'permissions' => [
                    'agents.view',
                    'agents.transactions.view',
                    'agents.transactions.reverse',
                ],
            ],

            'agent-approval-officer' => [
                'label' => 'Agent Approval Officer',
                'permissions' => [
                    'agents.view',
                    'agents.approve',
                    'agents.reject',
                    'agents.activate',
                    'agents.restrict',
                    'agents.suspend',
                    'agents.reactivate',
                    'agents.terminate',
                ],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $config['label']],
            );

            $permissionIds = Permission::whereIn(
                'name',
                $config['permissions']
            )->pluck('id');

            $role->permissions()->syncWithoutDetaching(
                $permissionIds
            );
        }

        $this->command->info(
            'Payments domain permissions and roles seeded.'
        );
    }
}
