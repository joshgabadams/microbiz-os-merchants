<?php

namespace App\Services\Accounting;

use App\Models\GlAccount;
use Exception;

class GlAccountResolver
{
    /**
     * Resolve a business key (e.g. VAULT_CASH)
     * into a GlAccount model.
     */
    public function resolve(string $key): GlAccount
    {
        $glCode = config("gl.{$key}");

        if (!$glCode) {
            throw new Exception(
                "GL configuration missing for key: {$key}"
            );
        }

        $account = GlAccount::where('gl_code', $glCode)
            ->where('disabled', false)
            ->first();

        if (!$account) {
            throw new Exception(
                "GL Account {$glCode} not found."
            );
        }

        return $account;
    }
}