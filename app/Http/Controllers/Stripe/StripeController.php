<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;


class StripeController extends Controller
{
    public function index_view ()
    {
        return view ('stripe.index');
    }

    public function stripePost(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'name' => 'required|string',
        'token' => 'required|string',
        'amount' => 'required|numeric|min:1'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'fail',
            'errors' => $validator->errors()
        ], 422);
    }

    Stripe::setApiKey(config('services.stripe.secret'));

    try {
        // Create or get customer
        $existingCustomers = Customer::all([
            'email' => $request->email,
            'limit' => 1,
        ]);

        $customer = count($existingCustomers->data)
            ? $existingCustomers->data[0]
            : Customer::create([
                'email' => $request->email,
                'name' => $request->name,
            ]);

        // Create a PaymentMethod from token
        $paymentMethod = PaymentMethod::create([
            'type' => 'card',
            'card' => ['token' => $request->token],
        ]);

        // Attach PaymentMethod to customer
        $paymentMethod->attach(['customer' => $customer->id]);

        // Create PaymentIntent
        $intent = PaymentIntent::create([
            'amount' => intval($request->amount * 100), // cents
            'currency' => 'usd',
            'customer' => $customer->id,
            'payment_method' => $paymentMethod->id,
            'confirmation_method' => 'manual',
            'confirm' => true,
            'return_url' => 'http://127.0.0.1:8000/',
        ]);

        if ($intent->status === 'requires_action' || $intent->status === 'requires_source_action') {
            return response()->json([
                'status' => 'requires_action',
                'client_secret' => $intent->client_secret,
                'message' => '3D Secure authentication required.'
            ]);
        } elseif ($intent->status === 'succeeded') {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment successful!'
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not completed. Status: ' . $intent->status
            ]);
        }
    } catch (\Exception $e) {
        Log::error('Stripe error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Stripe Error: ' . $e->getMessage()
        ], 500);
    }
}
public function stripeConfirm(Request $request)
{
    Stripe::setApiKey(config('services.stripe.secret'));

    try {
        $intent = PaymentIntent::retrieve($request->payment_intent_id);


        if ($intent->status === 'requires_confirmation') {
            $intent->confirm([
                    'return_url' => 'http://127.0.0.1:8000/',
                ]);
        }
        if ($intent->status === 'succeeded') {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment confirmed successfully.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Payment not completed. Status: ' . $intent->status
        ]);
    } catch (\Exception $e) {
        Log::error('Stripe confirmation error: ' . $e->getMessage());

        return response()->json([
            'status' => 'error',
            'message' => 'Stripe Error: ' . $e->getMessage()
        ], 500);
    }
}
    public function success_view()
    {
        return view('stripe.success');
    }

}