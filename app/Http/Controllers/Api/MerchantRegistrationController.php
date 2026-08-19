<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\ConfirmMerchantRegistrationRequest;
use App\Http\Requests\Merchant\PreviewMerchantAccountRequest;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Services\Fineract\FineractClientLookupService;
use App\Services\Payments\MerchantOnboardingService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Two-step merchant registration driven by an existing Fincore360 account
 * number: preview() looks the account up and returns it for the officer to
 * confirm, without writing anything locally; register() re-verifies that
 * same client server-side (never trusting whatever the frontend sends
 * back) before creating the local Customer/CustomerAccount and, through
 * the existing MerchantOnboardingService, the Merchant itself.
 */
class MerchantRegistrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FineractClientLookupService $fineract,
        protected MerchantOnboardingService $onboardingService,
    ) {
    }

    public function preview(PreviewMerchantAccountRequest $request)
    {
        $accountNumber = $request->validated('account_number');

        try {
            $matches = $this->fineract->searchByAccountNumber($accountNumber);
        } catch (RuntimeException $e) {
            return $this->error('Could not reach Fincore360: ' . $e->getMessage());
        }

        if (empty($matches)) {
            return $this->error("No Fincore360 client found for account number {$accountNumber}.", 404);
        }

        try {
            $client = $this->fineract->getClient($matches[0]['entityId']);
        } catch (RuntimeException $e) {
            return $this->error('Could not reach Fincore360: ' . $e->getMessage());
        }

        return $this->success(
            $this->presentClient($client),
            'Fincore360 client found.'
        );
    }

    public function register(ConfirmMerchantRegistrationRequest $request)
    {
        $data = $request->validated();

        try {
            // Re-fetch from Fincore360 using only the id -- the rest of
            // whatever the frontend sent back from preview() is never
            // trusted, so a tampered request can't register a merchant
            // under a different identity than the account it points at.
            $client = $this->fineract->getClient($data['fincore_client_id']);
        } catch (RuntimeException $e) {
            return $this->error('Could not verify the Fincore360 client: ' . $e->getMessage());
        }

        try {
            $merchant = DB::transaction(function () use ($data, $client, $request) {
                $customerAccount = $this->findOrCreateCustomerAccount($client);

                return $this->onboardingService->onboard(
                    array_merge($data, ['customer_account_id' => $customerAccount->id]),
                    $request->user()->id
                );
            });

            return $this->success($merchant, 'Merchant registered successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    protected function findOrCreateCustomerAccount(array $client): CustomerAccount
    {
        $existing = CustomerAccount::where('fincore_account_no', $client['accountNo'])->first();

        if ($existing) {
            return $existing;
        }

        // Entity-type Fincore360 clients (businesses) don't have a
        // first/last name split -- Customer's schema only has one, so the
        // full display name goes in first_name and last_name stays blank
        // rather than guessing a split that would misrepresent it.
        $customer = Customer::create([
            'customer_no' => 'FC-' . strtoupper(Str::random(8)),
            'first_name' => $client['displayName'] ?? $client['fullname'] ?? 'Unknown',
            'last_name' => '',
            'phone' => $client['mobileNo'] ?? null,
            'email' => $client['emailAddress'] ?? null,
            'status' => 'ACTIVE',
        ]);

        return CustomerAccount::create([
            'customer_id' => $customer->id,
            'fincore_client_id' => $client['id'],
            'fincore_account_no' => $client['accountNo'],
            'account_no' => $client['accountNo'],
            'account_type' => 'SAVINGS',
            'currency' => 'NGN',
            'status' => 'ACTIVE',
        ]);
    }

    protected function presentClient(array $client): array
    {
        return [
            'fincore_client_id' => $client['id'],
            'account_no' => $client['accountNo'],
            'external_id' => $client['externalId'] ?? null,
            'display_name' => $client['displayName'] ?? $client['fullname'] ?? null,
            'mobile_no' => $client['mobileNo'] ?? null,
            'email' => $client['emailAddress'] ?? null,
            'office_name' => $client['officeName'] ?? null,
            'legal_form' => $client['legalForm']['value'] ?? null,
            'status' => $client['status']['value'] ?? null,
            'active' => $client['active'] ?? false,
        ];
    }
}
