<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\CreateMerchantIdentityRequest;
use App\Models\Branch;
use App\Models\MerchantIdentity;
use App\Services\Fineract\FineractClientLookupService;
use App\Traits\ApiResponse;
use RuntimeException;

/**
 * Self-service merchant identity -- the "new applicant, no existing
 * Fincore360 account" path. Creates a real Fincore360 client, then a local
 * MerchantIdentity row linked to it. Deliberately scoped to identity +
 * Fincore360 creation only for now -- no password/OTP/session yet (built
 * in a later phase, per explicit sequencing).
 */
class MerchantIdentityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FineractClientLookupService $fineract,
    ) {
    }

    public function register(CreateMerchantIdentityRequest $request)
    {
        $data = $request->validated();

        // A retry with the same key must return the original result, not
        // create a second Fincore360 client -- same pattern already used by
        // merchant_transactions/MerchantPaymentService.
        $existing = MerchantIdentity::where('idempotency_key', $data['idempotency_key'])->first();

        if ($existing) {
            return $this->success(
                $existing,
                'Fincore360 account created and linked.',
                201
            );
        }

        // No branch selection in this flow -- self-registering applicants
        // don't pick an internal MicroBiz branch/office, so this always
        // resolves to whichever office is registered first (Head Office in
        // every Fincore360 instance seen this session). Revisit if this
        // ever needs to be more than one office.
        $officeId = Branch::query()->orderBy('office_id')->value('office_id');

        if (! $officeId) {
            return $this->error('No branch/office configured locally -- cannot assign a Fincore360 office.');
        }

        try {
            $client = $this->fineract->createClient([
                'office_id' => $officeId,
                'legal_form_id' => $data['legal_form'] === 'Person' ? 1 : 2,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'full_name' => $data['full_name'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return $this->error('Could not create the Fincore360 account: ' . $e->getMessage());
        }

        $identity = MerchantIdentity::create([
            'idempotency_key' => $data['idempotency_key'],
            'fincore_client_id' => $client['id'],
            'fincore_account_no' => $client['accountNo'] ?? null,
            'legal_form' => $client['legalForm']['value'] ?? $data['legal_form'],
            'display_name' => $client['displayName'] ?? $client['fullname'] ?? null,
            'phone' => $client['mobileNo'] ?? $data['phone'],
            'email' => $client['emailAddress'] ?? $data['email'] ?? null,
            'status' => 'PENDING',
        ]);

        return $this->success(
            $identity,
            'Fincore360 account created and linked.',
            201
        );
    }
}
