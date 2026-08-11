<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentTerminal;
use App\Services\GeoIp\GeoIpLookupService;
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

    public function __construct(
        protected GeoIpLookupService $geoIpLookupService
    ) {
    }

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

    public function heartbeat(AgentTerminal $terminal, array $data, ?string $ipAddress = null): AgentTerminal
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

        $updates = array_merge($updates, $this->evaluateIpLocation($terminal, $ipAddress));

        $terminal->update($updates);

        return $terminal->fresh();
    }

    public function checkLocation(
        AgentTerminal $terminal,
        float $latitude,
        float $longitude,
        ?string $ipAddress = null
    ): AgentTerminal {
        $updates = [
            'last_latitude' => $latitude,
            'last_longitude' => $longitude,
            'geo_fence_compliant' => $this->isWithinGeoFence($terminal, $latitude, $longitude),
        ];

        $updates = array_merge($updates, $this->evaluateIpLocation($terminal, $ipAddress));

        $terminal->update($updates);

        return $terminal->fresh();
    }

    /**
     * Agent Locator (issue #10) -- resolves the requesting IP to an
     * approximate location via MaxMind GeoLite2 and flags whether it
     * looks far from the terminal's own registered position. This is a
     * monitoring signal only: it never feeds geo_fence_compliant and
     * never blocks anything, since real enforcement happens on the
     * terminal's own hardware, outside this backend's control.
     *
     * @return array<string, mixed>
     */
    private function evaluateIpLocation(AgentTerminal $terminal, ?string $ipAddress): array
    {
        if ($ipAddress === null) {
            return [];
        }

        $result = $this->geoIpLookupService->lookup($ipAddress);

        $updates = [
            'last_ip_address' => $ipAddress,
            'ip_checked_at' => now(),
        ];

        if ($result === null) {
            return $updates;
        }

        $updates['ip_latitude'] = $result->latitude;
        $updates['ip_longitude'] = $result->longitude;
        $updates['ip_city'] = $result->city;
        $updates['ip_state'] = $result->state;
        $updates['ip_country'] = $result->country;

        if ($result->latitude !== null && $result->longitude !== null) {
            $distanceMetres = $this->haversineDistanceMetres(
                (float) $terminal->registered_latitude,
                (float) $terminal->registered_longitude,
                $result->latitude,
                $result->longitude
            );

            $updates['ip_location_mismatch'] = $distanceMetres > (config('geoip.mismatch_threshold_km') * 1000);
        }

        return $updates;
    }

    /**
     * Haversine distance (metres) between the terminal's registered point
     * and a given lat/lng, compared against its geo_fence_radius_metres.
     */
    private function isWithinGeoFence(AgentTerminal $terminal, float $latitude, float $longitude): bool
    {
        $distanceMetres = $this->haversineDistanceMetres(
            (float) $terminal->registered_latitude,
            (float) $terminal->registered_longitude,
            $latitude,
            $longitude
        );

        return $distanceMetres <= $terminal->geo_fence_radius_metres;
    }

    private function haversineDistanceMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMetres = 6371000;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMetres * $c;
    }
}
