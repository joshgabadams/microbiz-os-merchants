<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;

class AgentTransactionRiskService
{
    /**
     * Evaluate transaction risk before the transaction is processed.
     *
     * This service deliberately remains separate from AgentOperationGuard.
     * The operation guard owns hard operational preconditions such as agent
     * status, agreements, KYC, business-day state, terminal availability,
     * service enablement and transaction limits.
     *
     * This service owns transaction-risk signals that may evolve
     * independently as fraud and monitoring controls mature.
     */
    public function assess(
        Agent $agent,
        AgentTerminal $terminal,
        string $transactionType,
        float $amount
    ): array {
        $rules = [];

        $this->evaluateAgentRiskRating(
            $agent,
            $rules
        );

        $this->evaluateIpLocationMismatch(
            $terminal,
            $rules
        );

        $this->evaluateTransactionVelocity(
            $agent,
            $rules
        );

        $score = collect($rules)->sum('score');

        return [
            'version' => 1,
            'score' => $score,
            'level' => $this->riskLevel($score),
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'agent_risk_rating' => $agent->risk_rating,
            'rules' => $rules,
            'assessed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Agent onboarding/compliance risk is an input into transaction risk.
     *
     * LOW contributes no additional score, MEDIUM contributes a modest
     * score, and HIGH contributes a stronger risk signal.
     */
    protected function evaluateAgentRiskRating(
        Agent $agent,
        array &$rules
    ): void {
        $rating = strtoupper(
            (string) ($agent->risk_rating ?? 'MEDIUM')
        );

        $score = match ($rating) {
            'HIGH' => 40,
            'MEDIUM' => 15,
            default => 0,
        };

        $rules[] = [
            'rule' => 'AGENT_RISK_RATING',
            'matched' => $score > 0,
            'score' => $score,
            'value' => $rating,
        ];
    }

    /**
     * A known IP/location mismatch is a strong device-risk signal.
     *
     * The terminal model already persists this determination, so this
     * service consumes that trusted platform signal rather than attempting
     * to perform IP geolocation itself.
     */
    protected function evaluateIpLocationMismatch(
        AgentTerminal $terminal,
        array &$rules
    ): void {
        $matched = $terminal->ip_location_mismatch === true;

        $rules[] = [
            'rule' => 'IP_LOCATION_MISMATCH',
            'matched' => $matched,
            'score' => $matched ? 35 : 0,
        ];
    }

    /**
     * Detect unusually high recent transaction velocity for the agent.
     *
     * Only transactions that reached COMPLETED state count toward velocity.
     * Failed and merely initiated attempts do not inflate legitimate
     * transaction throughput.
     */
    protected function evaluateTransactionVelocity(
        Agent $agent,
        array &$rules
    ): void {
        $windowMinutes = (int) config(
            'agency.risk.velocity_window_minutes',
            10
        );

        $threshold = (int) config(
            'agency.risk.velocity_transaction_threshold',
            10
        );

        $recentTransactions = AgentTransaction::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'COMPLETED')
            ->where(
                'transaction_date',
                '>=',
                now()->subMinutes($windowMinutes)
            )
            ->count();

        $matched = $recentTransactions >= $threshold;

        $rules[] = [
            'rule' => 'TRANSACTION_VELOCITY',
            'matched' => $matched,
            'score' => $matched ? 30 : 0,
            'transaction_count' => $recentTransactions,
            'threshold' => $threshold,
            'window_minutes' => $windowMinutes,
        ];
    }

    /**
     * Convert the aggregate deterministic score into a simple risk level.
     *
     * These thresholds are configuration-backed so later policy changes do
     * not require rewriting transaction-processing code.
     */
    protected function riskLevel(int $score): string
    {
        $highThreshold = (int) config(
            'agency.risk.high_score_threshold',
            60
        );

        $mediumThreshold = (int) config(
            'agency.risk.medium_score_threshold',
            25
        );

        if ($score >= $highThreshold) {
            return 'HIGH';
        }

        if ($score >= $mediumThreshold) {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}
