<?php

use Illuminate\Support\Facades\Route;
use Th3JK\Treasurer\Http\Controllers\WebhookController;

Route::match(['GET', 'POST'], 'treasurer/webhooks/{gateway}/{token}', WebhookController::class)
    ->name('treasurer.webhook');
