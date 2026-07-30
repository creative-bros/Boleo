<?php

use App\Http\Controllers\Api\QuoteRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('external.quote')
    ->group(function (): void {
        Route::post('/solicitudes-cotizacion', [QuoteRequestController::class, 'store'])
            ->name('api.quote-requests.store');
    });
