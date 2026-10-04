<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PesapalPaymentController extends Controller
{
    use Processor;

    private ?object $configValues = null;
    private ?object $config = null;
    private string $baseUrl;

    public function __construct(private readonly PaymentRequest $payment)
    {
        $this->config = $this->payment_config('pesapal', 'payment_config');

        if (!is_null($this->config) && $this->config->mode === 'live') {
            $this->configValues = json_decode($this->config->live_values);
        } elseif (!is_null($this->config) && $this->config->mode === 'test') {
            $this->configValues = json_decode($this->config->test_values);
        }

        $this->baseUrl = !is_null($this->config) && $this->config->mode === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id'], 'is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if (!$this->isConfigured()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, [
                ['error_code' => 'pesapal_config', 'message' => 'Pesapal credentials are not configured.'],
            ]), 400);
        }

        $token = $this->requestToken();
        if (!$token) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, [
                ['error_code' => 'pesapal_token', 'message' => 'Unable to authenticate with Pesapal.'],
            ]), 400);
        }

        $notificationId = $this->configValues->ipn_id ?? null;
        if (!$notificationId) {
            $notificationId = $this->registerIpn($token);
        }

        if (!$notificationId) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, [
                ['error_code' => 'pesapal_ipn', 'message' => 'Pesapal IPN ID is required.'],
            ]), 400);
        }

        $payer = json_decode($data['payer_information']);
        $additionalData = json_decode($data['additional_data'] ?? '{}');
        $businessName = $additionalData->business_name ?? config('app.name', '6 Valley');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl . '/api/Transactions/SubmitOrderRequest', [
                    'id' => $data->id,
                    'currency' => strtoupper($data->currency_code),
                    'amount' => round((float)$data->payment_amount, 2),
                    'description' => substr($businessName . ' payment', 0, 100),
                    'callback_url' => route('pesapal.callback', ['payment_id' => $data->id]),
                    'cancellation_url' => route('pesapal.callback', ['payment_id' => $data->id, 'cancelled' => 1]),
                    'notification_id' => $notificationId,
                    'branch' => $businessName,
                    'billing_address' => [
                        'email_address' => $payer->email ?? '',
                        'phone_number' => $payer->phone ?? $payer->phone_number ?? '',
                        'country_code' => $this->configValues->country_code ?? 'TZ',
                        'first_name' => $payer->name ?? 'Customer',
                        'middle_name' => '',
                        'last_name' => '',
                        'line_1' => $payer->address ?? '',
                        'line_2' => '',
                        'city' => $payer->city ?? '',
                        'state' => '',
                        'postal_code' => '',
                        'zip_code' => '',
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::error('Pesapal order request failed.', [
                'payment_id' => $data->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, [
                ['error_code' => 'pesapal_order', 'message' => 'Unable to connect to Pesapal. Please check the server SSL certificate configuration.'],
            ]), 400);
        }

        $payload = $response->json();
        if ($response->successful() && !empty($payload['redirect_url'])) {
            return redirect()->away($payload['redirect_url']);
        }

        return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, [
            ['error_code' => 'pesapal_order', 'message' => $payload['message'] ?? 'Unable to create Pesapal order.'],
        ]), 400);
    }

    public function callback(Request $request)
    {
        $paymentData = $this->resolvePayment($request);
        if (!isset($paymentData)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($request->boolean('cancelled')) {
            return $this->failPayment($paymentData);
        }

        $status = $this->getTransactionStatus($request->get('OrderTrackingId'));
        if (strtoupper($status['payment_status_description'] ?? '') === 'COMPLETED' || (int)($status['status_code'] ?? 0) === 1) {
            return $this->completePayment($paymentData, $status['confirmation_code'] ?? $request->get('OrderTrackingId'));
        }

        return $this->failPayment($paymentData);
    }

    public function ipn(Request $request): JsonResponse
    {
        $paymentData = $this->resolvePayment($request);
        if (isset($paymentData)) {
            $status = $this->getTransactionStatus($request->get('OrderTrackingId'));
            if (strtoupper($status['payment_status_description'] ?? '') === 'COMPLETED' || (int)($status['status_code'] ?? 0) === 1) {
                $this->payment::where(['id' => $paymentData->id])->update([
                    'payment_method' => 'pesapal',
                    'is_paid' => 1,
                    'transaction_id' => $status['confirmation_code'] ?? $request->get('OrderTrackingId'),
                ]);

                $freshPayment = $this->payment::where(['id' => $paymentData->id])->first();
                if (isset($freshPayment) && function_exists($freshPayment->success_hook)) {
                    call_user_func($freshPayment->success_hook, $freshPayment);
                }
            }
        }

        return response()->json([
            'orderNotificationType' => $request->get('OrderNotificationType', 'IPNCHANGE'),
            'orderTrackingId' => $request->get('OrderTrackingId'),
            'orderMerchantReference' => $request->get('OrderMerchantReference'),
            'status' => 200,
        ]);
    }

    private function isConfigured(): bool
    {
        return isset($this->configValues->consumer_key, $this->configValues->consumer_secret)
            && $this->configValues->consumer_key !== ''
            && $this->configValues->consumer_secret !== '';
    }

    private function requestToken(): ?string
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->post($this->baseUrl . '/api/Auth/RequestToken', [
                    'consumer_key' => $this->configValues->consumer_key,
                    'consumer_secret' => $this->configValues->consumer_secret,
                ]);
        } catch (Throwable $exception) {
            Log::error('Pesapal token request failed.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!$response->successful()) {
            Log::warning('Pesapal token request was rejected.', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            return null;
        }

        $token = $response->json('token');
        if (!$token) {
            Log::warning('Pesapal token response did not include a token.', [
                'response' => $response->json() ?? $response->body(),
            ]);

            return null;
        }

        return $token;
    }

    private function registerIpn(string $token): ?string
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl . '/api/URLSetup/RegisterIPN', [
                    'url' => route('pesapal.ipn'),
                    'ipn_notification_type' => 'GET',
                ]);
        } catch (Throwable $exception) {
            Log::error('Pesapal IPN registration failed.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $ipnId = $response->successful() ? ($response->json('ipn_id') ?: null) : null;
        if ($ipnId) {
            $this->configValues->ipn_id = $ipnId;
            DB::table('addon_settings')
                ->where(['key_name' => 'pesapal', 'settings_type' => 'payment_config'])
                ->update([
                    'live_values' => json_encode($this->configValues),
                    'test_values' => json_encode($this->configValues),
                ]);
        }

        return $ipnId;
    }

    private function getTransactionStatus(?string $orderTrackingId): array
    {
        if (!$orderTrackingId || !$this->isConfigured()) {
            return [];
        }

        $token = $this->requestToken();
        if (!$token) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get($this->baseUrl . '/api/Transactions/GetTransactionStatus', [
                    'orderTrackingId' => $orderTrackingId,
                ]);
        } catch (Throwable $exception) {
            Log::error('Pesapal transaction status request failed.', [
                'order_tracking_id' => $orderTrackingId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        return $response->successful() ? $response->json() : [];
    }

    private function resolvePayment(Request $request): ?PaymentRequest
    {
        $paymentId = $request->get('payment_id') ?: $request->get('OrderMerchantReference');

        return $paymentId
            ? $this->payment::where(['id' => $paymentId])->first()
            : null;
    }

    private function completePayment(PaymentRequest $paymentData, ?string $transactionId)
    {
        $this->payment::where(['id' => $paymentData->id])->update([
            'payment_method' => 'pesapal',
            'is_paid' => 1,
            'transaction_id' => $transactionId,
        ]);

        $data = $this->payment::where(['id' => $paymentData->id])->first();
        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }

        return $this->payment_response($data, 'success');
    }

    private function failPayment(PaymentRequest $paymentData)
    {
        if (function_exists($paymentData->failure_hook)) {
            call_user_func($paymentData->failure_hook, $paymentData);
        }

        return $this->payment_response($paymentData, 'fail');
    }
}
