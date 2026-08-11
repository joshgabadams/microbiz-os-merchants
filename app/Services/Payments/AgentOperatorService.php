<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentOperatorService
{
    public function list(Agent $agent)
    {
        return $agent->operators()->get();
    }

    /**
     * @throws Exception
     */
    public function create(Agent $agent, array $data): AgentOperator
    {
        $location = AgentLocation::findOrFail($data['agent_location_id']);

        if ($location->agent_id !== $agent->id) {
            throw new Exception('The location does not belong to this agent.');
        }

        if ($agent->operators()
            ->where('agent_location_id', $location->id)
            ->where('user_id', $data['user_id'])
            ->exists()) {
            throw new Exception('This user is already assigned as an operator for this agent location.');
        }

        return DB::transaction(function () use ($agent, $location, $data) {
            return $agent->operators()->create([
                'agent_location_id' => $location->id,
                'user_id' => $data['user_id'],
                'role' => $data['role'],
                'status' => 'PENDING',
            ]);
        });
    }

    /**
     * @throws Exception
     */
    public function activate(AgentOperator $operator): AgentOperator
    {
        if ($operator->status === 'ACTIVE') {
            throw new Exception('This operator is already active.');
        }

        $operator->update([
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        return $operator->fresh();
    }

    /**
     * @throws Exception
     */
    public function suspend(AgentOperator $operator): AgentOperator
    {
        if ($operator->status !== 'ACTIVE') {
            throw new Exception('This operator is not active.');
        }

        $operator->update([
            'status' => 'SUSPENDED',
            'suspended_at' => now(),
        ]);

        return $operator->fresh();
    }
}
