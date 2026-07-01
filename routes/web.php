<?php

use Illuminate\Support\Facades\Route;
use App\Services\Ledger\LedgerService;


Route::get('/', function () {
    return view('welcome');
});

use App\Models\Branch;

Route::get('/test-branch', function () {

    return Branch::create([
        'name' => 'Abuja Main Branch',
        'code' => 'ABJ001',
        'office_id' => 1
    ]);

});

use App\Services\Fineract\FineractClient;

Route::get('/test-fineract', function () {

    return app(FineractClient::class)
        ->getOffices();

});

Route::get('/test-gls', function () {

    return app(
        \App\Services\Fineract\FineractClient::class
    )->getGlAccounts();

});

Route::get('/test-ledger', function (LedgerService $ledger) {

    return $ledger->post([

        'branch_id' => 1,

        'vault_id' => 1,

        'teller_id' => 1,

        'user_id' => 1,

        'transaction_type' => 'OPENING_CASH',

        'source_type' => 'SYSTEM',

        'source_id' => null,

        'debit' => 500000,

        'credit' => 0,

        'narration' => 'Opening Cash'

    ]);

});