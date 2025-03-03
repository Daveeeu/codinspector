<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::post('/webhook/order-updated', [WebhookController::class, 'handleOrderUpdated']);
