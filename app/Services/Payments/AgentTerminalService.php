<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentTerminal;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentTerminalService
{
    /**
     * CBN's PoS geo-fencing standard as of the 29 May 2026 circular
     * (raised from an earlier 10m standard, enforcement due 1 Aug 2026).
     */
    private const DEFAULT_GEO_FENCE_RADIUS_METRES = 70;

    /**
     * An agent must have cleared KYC, location verification and
     * compliance review before a terminal can be assigned -- CBN requires
     * due diligence before POS allocation. Terminal assignment itself is
     * one of the ACTIVE preconditions (Blueprint §6), so ACTIVE can't be
     * the minimum bar -- PENDING_APPROVAL onward is.
     */
    private const ELIGIBLE_STATUSES = [
        AgentStatus::PENDING_APPROVAL->value,
        AgentStatus::APPROVED->value,
        AgentStatus::AGREEMENT_PENDING->value,
        AgentStatus::TRAINING_PENDING->value,
        AgentStatus::TERMINAL_PENDING->value,
        AgentStatus::ACTIVE->value,
    ];

    public function list(Agent $agent)
    {
        return $agent->terminals()->get();
    }

    /**
     * @throws Exception
     */
    public function create(Agent $agent, array $data, int $assignedBy): AgentTerminal
    {
        if (! in_array($agent->status, self::ELIGIBLE_STATUSES, true)) {
            throw new Exception(
                "Agent {$agent->agent_code} has not cleared compliance review; cannot assign a terminal."
            );
        }

        $location = AgentLocation::findOrFail($data['agent_location_id']);

        if ($location->agent_id !== $agent->id) {
            throw new Exception('The location does not belong to this agent.');
        }

        return DB::transaction(function () use ($agent, $location, $data, $assignedBy) {
            return $agent->terminals()->create([
                'agent_location_id' => $location->id,
                'terminal_id' => $data['terminal_id'],
                'serial_number' => $data['serial_number'],
                'device_model' => $data['device_model'] ?? null,
                'provider' => $data['provider'] ?? null,
                'application_version' => $data['application_version'] ?? null,
                'status' => 'PENDING_ACTIVATION',
                'registered_latitude' => $data['registered_latitude'],
                'registered_longitude' => $data['registered_longitude'],
                'geo_fence_radius_metres' => $data['geo_fence_radius_metres'] ?? self::DEFAULT_GEO_FENCE_RADIUS_METRES,
                'assigned_by' => $assignedBy,
            ]);
        });
    }

    /**
     * Relocate an existing terminal to a different verified location
     * belonging to the same agent -- documented relocation per Blueprint
     * 2.3 (undocumented relocation must be prevented).
     *
     * @throws Exception
     */
    public function assignLocation(AgentTerminal $terminal, int $newLocationId): AgentTerminal
    {
        $location = AgentLocation::findOrFail($newLocationId);

        if ($location->agent_id !== $terminal->agent_id) {
            throw new Exception('The location does not belong to this terminal\'s agent.');
        }

        $terminal->update([
            'agent_location_id' => $location->id,
        ]);

        return $terminal->fresh();
    }

    /**
     * @throws Exception
     */
    public function activate(AgentTerminal $terminal): AgentTerminal
    {
        if ($terminal->status === 'ACTIVE') {
            throw new Exception("Terminal {$terminal->terminal_id} is already active.");
        }

        $terminal->update([
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        return $terminal->fresh();
    }

    /**
     * @throws Exception
     */
    public function suspend(AgentTerminal $terminal): AgentTerminal
    {
        if ($terminal->status !== 'ACTIVE') {
            throw new Exception("Terminal {$terminal->terminal_id} is not active.");
        }

        $terminal->update([
            'status' => 'SUSPENDED',
        ]);

        return $terminal->fresh();
    }

    public function heartbeat(AgentTerminal $terminal, array $data): AgentTerminal
    {
        $updates = ['last_heartbeat_at' => now()];

        if (isset($data['latitude'], $data['longitude'])) {
            $updates['last_latitude'] = $data['latitude'];
            $updates['last_longitude'] = $data['longitude'];
            $updates['geo_fence_compliant'] = $this->isWithinGeoFence(
                $terminal,
                (float) $data['latitude'],
                (float) $data['longitude']
            );
        }

        $terminal->update($updates);

        return $terminal->fresh();
    }

    public function checkLocation(AgentTerminal $terminal, float $latitude, float $longitude): AgentTerminal
    {
        $terminal->update([
            'last_latitude' => $latitude,
            'last_longitude' => $longitude,
            'geo_fence_compliant' => $this->isWithinGeoFence($terminal, $latitude, $longitude),
        ]);

        return $terminal->fresh();
    }

    /**
     * Haversine distance (metres) between the terminal's registered point
     * and a given lat/lng, compared against its geo_fence_radius_metres.
     */
    private function isWithinGeoFence(AgentTerminal $terminal, float $latitude, float $longitude): bool
    {
        $earthRadiusMetres = 6371000;

        $lat1 = deg2rad((float) $terminal->registered_latitude);
        $lat2 = deg2rad($latitude);
        $deltaLat = deg2rad($latitude - (float) $terminal->registered_latitude);
        $deltaLng = deg2rad($longitude - (float) $terminal->registered_longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distanceMetres = $earthRadiusMetres * $c;

        return $distanceMetres <= $terminal->geo_fence_radius_metres;
    }
}
