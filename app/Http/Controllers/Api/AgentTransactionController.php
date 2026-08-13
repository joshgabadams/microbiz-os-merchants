<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentCashInRequest;
use App\Http\Requests\Agent\AgentCashOutRequest;
use App\Http\Requests\Agent\AgentTransferRequest;
use App\Models\Agent;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\CustomerAccount;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentCashOutService;
use App\Services\Payments\AgentTransferService;
use App\Traits\ApiResponse;
use Exception;

class AgentTransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AgentCashInService $cashInService,
        protected AgentCashOutService $cashOutService,
        protected AgentTransferService $transferService
    ) {
    }

    public function list(Agent $agent)
    {
        return $this->success(
            $agent->transactions()
                ->with(['terminal', 'operator'])
                ->orderByDesc('created_at')
                ->get(),
            'Agent transactions retrieved successfully.'
        );
    }

    public function cashIn(
        Agent $agent,
        AgentCashInRequest $request
    ) {
        try {
            $data = $request->validated();

            $operator = AgentOperator::findOrFail(
                $data['operator_id']
            );

            $terminal = AgentTerminal::findOrFail(
                $data['terminal_id']
            );

            $customerAccount = CustomerAccount::findOrFail(
                $data['customer_account_id']
            );

            $this->assertOperatorBelongsToAgent(
                $agent,
                $operator
            );

            $transaction = $this->cashInService->cashIn(
                $operator,
                $terminal,
                $customerAccount,
                (float) $data['amount'],
                $data['idempotency_key'],
                (float) $data['latitude'],
                (float) $data['longitude'],
                $request->user()->id,
                $data['customer_reference'] ?? null,
                $data['narration'] ?? null
            );

            return $this->success(
                $transaction,
                'Agent cash-in completed successfully.',
                201
            );
        } catch (Exception $e) {
            return $this->error(
                $e->getMessage()
            );
        }
    }

    public function cashOut(
        Agent $agent,
        AgentCashOutRequest $request
    ) {
        try {
            $data = $request->validated();

            $operator = AgentOperator::findOrFail(
                $data['operator_id']
            );

            $terminal = AgentTerminal::findOrFail(
                $data['terminal_id']
            );

            $customerAccount = CustomerAccount::findOrFail(
                $data['customer_account_id']
            );

            $this->assertOperatorBelongsToAgent(
                $agent,
                $operator
            );

            $transaction = $this->cashOutService->cashOut(
                $operator,
                $terminal,
                $customerAccount,
                (float) $data['amount'],
                $data['idempotency_key'],
                (float) $data['latitude'],
                (float) $data['longitude'],
                $request->user()->id,
                (bool) $data['customer_authenticated'],
                $data['customer_reference'] ?? null,
                $data['narration'] ?? null
            );

            return $this->success(
                $transaction,
                'Agent cash-out completed successfully.',
                201
            );
        } catch (Exception $e) {
            return $this->error(
                $e->getMessage()
            );
        }
    }

    public function transfer(
        Agent $agent,
        AgentTransferRequest $request
    ) {
        try {
            $data = $request->validated();

            $operator = AgentOperator::findOrFail(
                $data['operator_id']
            );

            $terminal = AgentTerminal::findOrFail(
                $data['terminal_id']
            );

            $fromAccount = CustomerAccount::findOrFail(
                $data['from_account_id']
            );

            $toAccount = CustomerAccount::findOrFail(
                $data['to_account_id']
            );

            $this->assertOperatorBelongsToAgent(
                $agent,
                $operator
            );

            $transaction = $this->transferService->transfer(
                $operator,
                $terminal,
                $fromAccount,
                $toAccount,
                (float) $data['amount'],
                $data['idempotency_key'],
                (float) $data['latitude'],
                (float) $data['longitude'],
                $request->user()->id,
                $data['narration'] ?? null
            );

            return $this->success(
                $transaction,
                'Agent transfer completed successfully.',
                201
            );
        } catch (Exception $e) {
            return $this->error(
                $e->getMessage()
            );
        }
    }

    /**
     * Protect the route-level agent boundary.
     *
     * The transaction services derive the agent from the operator.
     * Therefore the API must ensure an operator supplied in the payload
     * cannot be used against another agent's route.
     *
     * @throws Exception
     */
    protected function assertOperatorBelongsToAgent(
        Agent $agent,
        AgentOperator $operator
    ): void {
        if ($operator->agent_id !== $agent->id) {
            throw new Exception(
                'The selected operator does not belong to this agent.'
            );
        }
    }
}