<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TerminalLookupController extends Controller
{
    public function show(string $lookupType, string $identifier): JsonResponse
    {
        $validIdentifier = $lookupType === 'terminal-id'
            ? preg_match('/^\d{4,32}$/', $identifier)
            : preg_match('/^[A-Za-z0-9_-]{4,64}$/', $identifier);

        if (! $validIdentifier) {
            return response()->json([
                'message' => $lookupType === 'terminal-id'
                    ? 'The terminal ID must contain between 4 and 32 digits.'
                    : 'Enter a valid serial number.',
            ], 422);
        }

        $baseUrl = rtrim((string) config(
            $lookupType === 'terminal-id'
                ? 'services.terminal_lookup.serial_url'
                : 'services.terminal_lookup.url'
        ), '/');

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get("{$baseUrl}/".rawurlencode($identifier));
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'The terminal service is currently unavailable. Please try again.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => $response->status() === 404
                    ? 'No terminal was found for that ID.'
                    : 'The terminal service could not complete the lookup.',
            ], $response->status() === 404 ? 404 : 502);
        }

        $terminal = $response->json();

        if (! is_array($terminal)) {
            return response()->json([
                'message' => 'The terminal service returned an unexpected response.',
            ], 502);
        }

        $terminalId = $terminal['terminalid'] ?? $terminal['terminal_id'] ?? null;
        $serialNumber = $terminal['serialid']
            ?? $terminal['serialnumber']
            ?? $terminal['serial_number']
            ?? $terminal['serialno']
            ?? $terminal['serial']
            ?? null;

        if ($lookupType === 'terminal-id') {
            $terminalId ??= $identifier;
        } else {
            $serialNumber ??= $identifier;
        }

        $expectedResult = $lookupType === 'terminal-id' ? $serialNumber : $terminalId;

        if ($expectedResult === null || $expectedResult === '') {
            return response()->json([
                'message' => 'The terminal service returned an unexpected response.',
            ], 502);
        }

        return response()->json([
            'terminalid' => $terminalId !== null ? (string) $terminalId : null,
            'serialnumber' => $serialNumber !== null ? (string) $serialNumber : null,
            'manufacturer' => (string) ($terminal['manufacturer'] ?? 'Unknown'),
            'status' => (string) ($terminal['status'] ?? 'success'),
        ]);
    }
}
