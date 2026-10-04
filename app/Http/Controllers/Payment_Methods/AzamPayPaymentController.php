<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AzamPayPaymentController extends Controller
{
    use Processor;

    private $config_values;
    private PaymentRequest $payment;
    private $base_url;
    private $auth_url;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('azam_pay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
            $this->base_url = 'https://checkout.azampay.co.tz';
            $this->auth_url = 'https://authenticator.azampay.co.tz';
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
            $this->base_url = 'https://sandbox.azampay.co.tz';
            $this->auth_url = 'https://authenticator-sandbox.azampay.co.tz';
        }
        $this->payment = $payment;
    }

    private function getToken(): ?string
    {
        $response = Http::timeout(30)->post("{$this->auth_url}/AppRegistration/GenerateToken", [
            'appName' => $this->config_values->app_name ?? '',
            'clientId' => $this->config_values->client_id ?? '',
            'clientSecret' => $this->config_values->client_secret ?? '',
        ]);

        if ($response->successful()) {
            $body = $response->json();
            return $body['data']['accessToken'] ?? null;
        }

        Log::error('AzamPay token failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return null;
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json(['error' => 'Payment record not found'], 404);
        }

        if (empty($this->config_values->app_name) || empty($this->config_values->client_id) || empty($this->config_values->client_secret) || empty($this->config_values->api_key)) {
            return response()->json(['error' => 'AzamPay credentials not configured. Please set app_name, client_id, client_secret, and api_key in admin settings.'], 400);
        }

        $accessToken = $this->getToken();
        if (!$accessToken) {
            return response()->json(['error' => 'Failed to authenticate with AzamPay. Check your API credentials.'], 400);
        }

        $successUrl = route('azam-pay.success', ['payment_id' => $data->id]);
        $failUrl = route('azam-pay.fail', ['payment_id' => $data->id]);

        $payload = [
            'appName' => $this->config_values->app_name,
            'clientId' => $this->config_values->client_id,
            'vendorId' => $this->config_values->client_id,
            'language' => 'sw',
            'currency' => $data->currency_code ?? 'TZS',
            'externalId' => substr($data->id, 0, 30),
            'requestOrigin' => url('/'),
            'redirectFailURL' => $failUrl,
            'redirectSuccessURL' => $successUrl,
            'vendorName' => $this->config_values->app_name,
            'amount' => (string)round($data->payment_amount, 2),
            'cart' => [
                'items' => [
                    [
                        'name' => 'Order Payment',
                    ]
                ]
            ],
        ];

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-API-Key' => $this->config_values->api_key,
            'Content-Type' => 'application/json',
        ])->post("{$this->base_url}/api/v1/Partner/PostCheckout", $payload);

        if ($response->successful()) {
            $body = $response->body();
            if (!empty($body)) {
                return redirect()->away(trim($body));
            }
        }

        Log::error('AzamPay postcheckout failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        $errBody = $response->json();
        return response()->json([
            'error' => 'AzamPay checkout failed',
            'details' => $errBody,
        ], 400);
    }

    public function success(Request $request)
    {
        $payment_id = $request->payment_id;
        $paymentData = $this->payment::where(['id' => $payment_id])->first();

        if (!$paymentData) {
            return redirect()->route('payment-fail');
        }

        $paymentData->update([
            'payment_method' => 'azam_pay',
            'is_paid' => 1,
            'transaction_id' => $request->transactionId ?? $request->payment_id,
        ]);

        $data = $this->payment::where(['id' => $payment_id])->first();
        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }
        return $this->payment_response($data, 'success');
    }

    public function fail(Request $request)
    {
        $payment_id = $request->payment_id;
        $paymentData = $this->payment::where(['id' => $payment_id])->first();

        if ($paymentData && function_exists($paymentData->failure_hook)) {
            call_user_func($paymentData->failure_hook, $paymentData);
        }
        return $this->payment_response($paymentData, 'fail');
    }

    public function callback(Request $request)
    {
        $payment_id = $request->payment_id ?? $request->externalId;
        $paymentData = $this->payment::where(['id' => $payment_id])->first();

        if (!$paymentData) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($paymentData->is_paid == 1) {
            return response()->json(['message' => 'Already processed']);
        }

        $paymentData->update([
            'payment_method' => 'azam_pay',
            'is_paid' => 1,
            'transaction_id' => $request->transactionId ?? $payment_id,
        ]);

        $data = $this->payment::where(['id' => $payment_id])->first();
        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }
        return response()->json(['message' => 'success']);
    }
}
