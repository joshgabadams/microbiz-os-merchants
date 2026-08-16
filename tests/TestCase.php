<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The API auth middleware is stateless HTTP Basic
     * (AuthenticateBasicOnce), not Sanctum -- attach the header directly
     * instead of Sanctum::actingAs(), which only binds the unused
     * 'sanctum' guard and has no effect on requests anymore.
     * $password defaults to UserFactory's own default plaintext.
     */
    protected function actingAsBasicAuth(
        User $user,
        string $password = 'password'
    ): static {
        return $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode(
                "{$user->email}:{$password}"
            ),
        ]);
    }
}
