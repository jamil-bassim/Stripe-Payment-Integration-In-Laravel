<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Stripe\StripeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/



Route::get('/', [StripeController::class, 'index_view'])->name('stripe.view');
Route::post('/stripe-post', [StripeController::class, 'stripePost'])->name('stripe.post');
Route::post('/stripe/confirm', [StripeController::class, 'stripeConfirm'])->name('stripe.confirm');
Route::get('/stripe/success', [StripeController::class, 'success_view'])->name('stripe.success');


