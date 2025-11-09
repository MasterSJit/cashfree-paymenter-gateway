<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\CashfreeV2\CashfreeV2;

Route::post(
    '/extensions/gateways/cashfreev2/webhook',
    [CashfreeV2::class, 'webhook']
)->name('extensions.gateways.cashfreev2.webhook');

Route::get(
    '/extensions/gateways/cashfreev2/callback/{invoiceId}',
    [CashfreeV2::class, 'callback']
)->name('extensions.gateways.cashfreev2.callback');
