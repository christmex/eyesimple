<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\OrderController;

Route::apiResource('/orders', OrderController::class)
    ->only(['store']);