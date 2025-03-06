<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::post('/webhook/order-updated', [WebhookController::class, 'handleOrderUpdated']);

Route::post('/webhook/customer-data-request', [WebhookController::class, 'handleCustomerDataRequest']);
Route::post('/webhook/customer-data-erasure', [WebhookController::class, 'handleCustomerDataErasure']);
Route::post('/webhook/shop-data-erasure', [WebhookController::class, 'handleShopDataErasure']);
