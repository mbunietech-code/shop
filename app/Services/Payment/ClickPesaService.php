<?php

namespace App\Services\Payment;

use App\Models\ClickPesaWebhookLog;
use App\Models\PaymentRequest;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ClickPesaService
{
    public const PROVIDER = 'click_pesa';
    private const BASE_URL = 'https://api.clickpesa.com/third-parties';

    private array $settings;
    private ?Setting $setting;

    public function __construct(?Setting $setting = null)
    {
        $this->setting = $setting ?: Setting::where('key_name', self::PROVIDER)
            ->where('settings_type', 'payment_config')
            ->first();

        $values = [];
        if ($this->setting) {
            $mode = $this->setting->mode ?: 'live';
            $values = $mode === 'test'
                ? ($this->setting->test_values ?: [])
                : ($this->setting->live_values ?: []);
        }

        $this->settings = is_array($values) ? $values : (array) $values;
    }

    public static function paymentMethods(): array
    {
        return [
            'airtel_money' => [
                'label' => 'Airtel Money',
                'type' => 'mobile_money',
                'provider_hint' => 'AIRTEL-MONEY',
            ],
            'mpesa' => [
                'label' => 'M-Pesa / Vodacom',
                'type' => 'mobile_money',
                'provider_hint' => 'M-PESA',
            ],
            'mixx_by_yas' => [
                'label' => 'Mixx by Yas',
                'type' => 'mobile_money',
                'provider_hint' => 'TIGO-PESA',
            ],
            'halopesa' => [
                'label' => 'HaloPesa',
                'type' => 'mobile_money',
                'provider_hint' => 'HALOPESA',
            ],
            'control_number' => [
                'label' => 'CRDB / Control Number',
                'type' => 'billpay',
                'provider_hint' => 'BILLPAY',
            ],
        ];
    }

    public static function defaultEnabledMethods(): array
    {
        return array_keys(self::paymentMethods());
    }

    public function enabledPaymentMethods(): array
    {
        $configured = $this->settings['enabled_methods'] ?? self::defaultEnabledMethods();
        if (is_string($configured)) {
            $decoded = json_decode($configured, true);
            $configured = json_last_error() === JSON_ERROR_NONE ? $decoded : explode(',', $configured);
        }

        $configured = is_array($configured) ? $configured : self::defaultEnabledMethods();
        $methods = self::paymentMethods();

        return array_intersect_key($methods, array_flip($configured));
    }

    public static function encryptCredential(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (str_starts_with($value, 'enc:')) {
            return $value;
        }

        return 'enc:' . Crypt::encryptString($value);
    }

    public static function decryptCredential(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        if (!str_starts_with($value, 'enc:')) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, 4));
        } catch (\Throwable) {
            return '';
        }
    }

    public function configured(): bool
    {
        return $this->credential('client_id') !== '' && $this->credential('api_key') !== '';
    }

    public function mode(): string
    {
        return $this->setting?->mode ?: ($this->settings['mode'] ?? 'live');
    }

    public function webhookUrl(): string
    {
        return url('/api/payments/clickpesa/webhook');
    }

    public function credential(string $key): string
    {
        return self::decryptCredential($this->settings[$key] ?? '');
    }

    public function generateToken(): string
    {
        if (!$this->configured()) {
            throw new RuntimeException('ClickPesa credentials are not configured.');
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'client-id' => $this->credential('client_id'),
                'api-key' => $this->credential('api_key'),
            ])
            ->post(self::BASE_URL . '/generate-token');

        if (!$response->successful()) {
            Log::error('ClickPesa token generation failed', [
                'status' => $response->status(),
                'body' => $this->safeResponseBody($response),
            ]);
            throw new RuntimeException('Unable to authenticate with ClickPesa.');
        }

        $token = $response->json('token');
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('ClickPesa token response did not contain a token.');
        }

        return $token;
    }

    public function initiateUssdPush(PaymentRequest $payment, string $phoneNumber, string $selectedMethod): array
    {
        $this->assertMethod($selectedMethod, 'mobile_money');
        $normalizedPhone = self::normalizeTanzanianPhone($phoneNumber);
        $orderReference = $this->ensureOrderReference($payment);

        $payload = [
            'amount' => $this->formatAmount($payment->payment_amount),
            'currency' => $payment->currency_code ?: 'TZS',
            'orderReference' => $orderReference,
            'phoneNumber' => $normalizedPhone,
        ];

        $response = $this->post('/payments/initiate-ussd-push-request', $this->withChecksum($payload));
        $body = $response->json() ?: [];

        if (!$response->successful()) {
            $this->updatePaymentMetadata($payment, [
                'payment_status' => 'failed',
                'payment_phone' => $normalizedPhone,
                'provider_payment_method' => $selectedMethod,
                'last_api_response' => $body ?: $this->safeResponseBody($response),
            ]);
            throw new RuntimeException($this->customerSafeMessage($response, 'ClickPesa payment request failed.'));
        }

        $this->updatePaymentMetadata($payment, [
            'payment_phone' => $normalizedPhone,
            'payment_status' => strtolower($body['status'] ?? 'processing'),
            'provider_reference' => $body['orderReference'] ?? $orderReference,
            'provider_transaction_id' => $body['id'] ?? null,
            'provider_payment_method' => $body['channel'] ?? $selectedMethod,
            'payment_currency' => $body['collectedCurrency'] ?? ($payment->currency_code ?: 'TZS'),
            'payment_metadata' => $this->mergeMetadata($payment, [
                'clickpesa_selected_method' => $selectedMethod,
                'clickpesa_selected_label' => self::paymentMethods()[$selectedMethod]['label'],
                'clickpesa_initiate_response' => $body,
            ]),
        ]);

        return $body;
    }

    public function createControlNumber(PaymentRequest $payment, string $phoneNumber, string $selectedMethod): array
    {
        $this->assertMethod($selectedMethod, 'billpay');
        $normalizedPhone = self::normalizeTanzanianPhone($phoneNumber);
        $orderReference = $this->ensureOrderReference($payment);
        $payer = json_decode($payment->payer_information ?: '{}', true) ?: [];

        $payload = [
            'customerName' => $payer['name'] ?? 'Customer',
            'customerPhone' => $normalizedPhone,
            'billDescription' => 'Order payment ' . substr(str_replace('-', '', $payment->id), 0, 12),
            'billPaymentMode' => $this->settings['bill_payment_mode'] ?? 'EXACT',
            'billAmount' => (float) $this->formatAmount($payment->payment_amount),
            'billReference' => $orderReference,
        ];

        if (!empty($payer['email'])) {
            $payload['customerEmail'] = $payer['email'];
        }

        $response = $this->post('/billpay/create-customer-control-number', $this->withChecksum($payload));
        $body = $response->json() ?: [];

        if (!$response->successful() || empty($body['billPayNumber'])) {
            $this->updatePaymentMetadata($payment, [
                'payment_status' => 'failed',
                'payment_phone' => $normalizedPhone,
                'provider_payment_method' => $selectedMethod,
                'last_api_response' => $body ?: $this->safeResponseBody($response),
            ]);
            throw new RuntimeException($this->customerSafeMessage($response, 'ClickPesa control number request failed.'));
        }

        $this->updatePaymentMetadata($payment, [
            'payment_phone' => $normalizedPhone,
            'payment_status' => 'pending',
            'provider_reference' => $body['billReference'] ?? $orderReference,
            'control_number' => $body['billPayNumber'],
            'provider_payment_method' => 'BILLPAY',
            'payment_currency' => $payment->currency_code ?: 'TZS',
            'payment_metadata' => $this->mergeMetadata($payment, [
                'clickpesa_selected_method' => $selectedMethod,
                'clickpesa_selected_label' => self::paymentMethods()[$selectedMethod]['label'],
                'clickpesa_billpay_response' => $body,
            ]),
        ]);

        return $body;
    }

    public function queryPayment(PaymentRequest $payment): array
    {
        $reference = $payment->provider_reference ?: $this->ensureOrderReference($payment);
        $response = $this->get('/payments/' . rawurlencode($reference));

        if (!$response->successful()) {
            return [
                'status' => $payment->payment_status ?: 'processing',
                'response' => $response->json() ?: $this->safeResponseBody($response),
            ];
        }

        $payments = $response->json() ?: [];
        $providerPayment = is_array($payments) ? ($payments[0] ?? []) : [];

        if ($providerPayment) {
            $this->reconcileProviderPayment($payment, $providerPayment);
        }

        return [
            'status' => $providerPayment['status'] ?? ($payment->fresh()->payment_status ?: 'processing'),
            'response' => $providerPayment ?: $payments,
        ];
    }

    public function processWebhook(array $payload, array $headers = []): array
    {
        $event = $payload['event'] ?? 'UNKNOWN';
        $data = $payload['data'] ?? [];
        $requestIdentifier = $headers['x-request-id'][0]
            ?? $headers['x-correlation-id'][0]
            ?? ($data['id'] ?? Str::uuid()->toString());

        $log = ClickPesaWebhookLog::create([
            'provider' => self::PROVIDER,
            'event' => $event,
            'request_identifier' => $requestIdentifier,
            'provider_reference' => $data['orderReference'] ?? null,
            'payload' => $this->sanitizePayload($payload),
            'processing_status' => 'received',
            'received_at' => now(),
        ]);

        try {
            $this->assertValidWebhookChecksum($payload);

            if (!in_array($event, ['PAYMENT RECEIVED', 'PAYMENT FAILED'], true)) {
                $log->update([
                    'processing_status' => 'ignored',
                    'processed_at' => now(),
                ]);

                return ['processed' => false, 'message' => 'Ignored event'];
            }

            $reference = $data['orderReference'] ?? null;
            if (!$reference) {
                throw new RuntimeException('Webhook payload missing orderReference.');
            }

            $payment = PaymentRequest::where('provider_reference', $reference)
                ->orWhere('control_number', $reference)
                ->orWhere('id', $reference)
                ->first();

            if (!$payment) {
                throw new RuntimeException('Payment request not found for ClickPesa reference.');
            }

            $this->reconcileProviderPayment($payment, $data);

            $log->update([
                'payment_request_id' => $payment->id,
                'provider_transaction_id' => $data['id'] ?? null,
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            return [
                'processed' => true,
                'payment' => $payment->fresh(),
            ];
        } catch (\Throwable $exception) {
            $log->update([
                'processing_status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function reconcileProviderPayment(PaymentRequest $payment, array $providerPayment): PaymentRequest
    {
        $status = strtoupper((string) ($providerPayment['status'] ?? 'PROCESSING'));
        $providerAmount = $providerPayment['collectedAmount'] ?? null;
        $providerCurrency = $providerPayment['collectedCurrency'] ?? ($payment->currency_code ?: 'TZS');
        $expectedCurrency = $payment->currency_code ?: 'TZS';
        $metadata = $this->mergeMetadata($payment, [
            'clickpesa_last_verification' => $providerPayment,
        ]);

        if (in_array($status, ['SUCCESS', 'SETTLED'], true)) {
            if (!$this->amountMatches($payment->payment_amount, $providerAmount)) {
                $this->updatePaymentMetadata($payment, [
                    'payment_status' => 'review_required',
                    'verification_result' => 'amount_mismatch',
                    'payment_metadata' => $metadata,
                ]);
                return $payment->fresh();
            }

            if (strtoupper((string) $providerCurrency) !== strtoupper((string) $expectedCurrency)) {
                $this->updatePaymentMetadata($payment, [
                    'payment_status' => 'review_required',
                    'verification_result' => 'currency_mismatch',
                    'payment_metadata' => $metadata,
                ]);
                return $payment->fresh();
            }

            $this->updatePaymentMetadata($payment, [
                'payment_method' => self::PROVIDER,
                'is_paid' => 1,
                'payment_status' => 'paid',
                'transaction_id' => $providerPayment['paymentReference'] ?? $providerPayment['id'] ?? $payment->transaction_id,
                'provider_transaction_id' => $providerPayment['id'] ?? $payment->provider_transaction_id,
                'provider_payment_method' => $providerPayment['channel'] ?? $payment->provider_payment_method,
                'provider_reference' => $providerPayment['orderReference'] ?? $payment->provider_reference,
                'payment_phone' => $providerPayment['paymentPhoneNumber'] ?? ($providerPayment['customer']['customerPhoneNumber'] ?? $payment->payment_phone),
                'payment_currency' => $providerCurrency,
                'verification_result' => 'verified',
                'verified_at' => now(),
                'paid_at' => $payment->paid_at ?: now(),
                'payment_metadata' => $metadata,
            ]);

            return $payment->fresh();
        }

        if ($status === 'FAILED') {
            $this->updatePaymentMetadata($payment, [
                'payment_status' => 'failed',
                'provider_transaction_id' => $providerPayment['id'] ?? $payment->provider_transaction_id,
                'provider_payment_method' => $providerPayment['channel'] ?? $payment->provider_payment_method,
                'verification_result' => $providerPayment['message'] ?? 'failed',
                'payment_metadata' => $metadata,
                'failed_at' => now(),
            ]);

            return $payment->fresh();
        }

        $this->updatePaymentMetadata($payment, [
            'payment_status' => strtolower($status),
            'provider_transaction_id' => $providerPayment['id'] ?? $payment->provider_transaction_id,
            'provider_payment_method' => $providerPayment['channel'] ?? $payment->provider_payment_method,
            'payment_metadata' => $metadata,
        ]);

        return $payment->fresh();
    }

    public static function normalizeTanzanianPhone(?string $phoneNumber): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phoneNumber);

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '255' . substr($phone, 1);
        }

        if (strlen($phone) === 9 && preg_match('/^[67]/', $phone)) {
            $phone = '255' . $phone;
        }

        if (!preg_match('/^255[67][0-9]{8}$/', $phone)) {
            throw new InvalidArgumentException('Invalid Tanzanian phone number.');
        }

        return $phone;
    }

    public function createPayloadChecksum(array $payload, ?string $secret = null): string
    {
        $payload = $this->withoutChecksumFields($payload);
        $canonicalPayload = $this->canonicalize($payload);
        $payloadString = json_encode($canonicalPayload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payloadString, $secret ?: $this->credential('api_secret'));
    }

    public function validatePayloadChecksum(array $payload, string $receivedChecksum, ?string $secret = null): bool
    {
        if ($receivedChecksum === '') {
            return false;
        }

        return hash_equals($this->createPayloadChecksum($payload, $secret), $receivedChecksum);
    }

    private function assertValidWebhookChecksum(array $payload): void
    {
        $secret = $this->credential('webhook_secret') ?: $this->credential('api_secret');
        $checksumRequired = (bool) ($this->settings['checksum_enabled'] ?? false);
        $received = (string) ($payload['checksum'] ?? '');

        if ($secret === '' && !$checksumRequired) {
            return;
        }

        if ($received === '') {
            throw new RuntimeException('ClickPesa webhook checksum is missing.');
        }

        if (!$this->validatePayloadChecksum($payload, $received, $secret)) {
            throw new RuntimeException('ClickPesa webhook checksum is invalid.');
        }
    }

    private function post(string $path, array $payload): Response
    {
        return Http::timeout(30)
            ->withHeaders($this->authHeaders())
            ->post(self::BASE_URL . $path, $payload);
    }

    private function get(string $path): Response
    {
        return Http::timeout(30)
            ->withHeaders($this->authHeaders())
            ->get(self::BASE_URL . $path);
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => $this->generateToken(),
            'client-id' => $this->credential('client_id'),
            'Content-Type' => 'application/json',
        ];
    }

    private function withChecksum(array $payload): array
    {
        if (!($this->settings['checksum_enabled'] ?? false)) {
            return $payload;
        }

        $secret = $this->credential('api_secret');
        if ($secret === '') {
            throw new RuntimeException('ClickPesa checksum is enabled but API secret is missing.');
        }

        $payload['checksum'] = $this->createPayloadChecksum($payload, $secret);
        $payload['checksumMethod'] = 'canonical';

        return $payload;
    }

    private function ensureOrderReference(PaymentRequest $payment): string
    {
        if (!empty($payment->provider_reference)) {
            return $payment->provider_reference;
        }

        $reference = 'CP' . substr(preg_replace('/[^A-Za-z0-9]/', '', $payment->id), 0, 18);
        $this->updatePaymentMetadata($payment, [
            'provider_reference' => $reference,
        ]);

        return $reference;
    }

    private function assertMethod(string $method, string $type): void
    {
        $methods = $this->enabledPaymentMethods();
        if (!isset($methods[$method]) || $methods[$method]['type'] !== $type) {
            throw new InvalidArgumentException('Unsupported ClickPesa payment method.');
        }
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function amountMatches(mixed $expected, mixed $actual): bool
    {
        if ($actual === null || $actual === '') {
            return false;
        }

        return abs(((float) $expected) - ((float) $actual)) < 0.01;
    }

    private function mergeMetadata(PaymentRequest $payment, array $data): array
    {
        $existing = [];
        if (!empty($payment->payment_metadata)) {
            $existing = is_array($payment->payment_metadata)
                ? $payment->payment_metadata
                : (json_decode($payment->payment_metadata, true) ?: []);
        }

        return array_replace_recursive($existing, $data);
    }

    private function updatePaymentMetadata(PaymentRequest $payment, array $attributes): void
    {
        if (array_key_exists('last_api_response', $attributes)) {
            $metadata = $this->mergeMetadata($payment, [
                'clickpesa_last_api_response' => $attributes['last_api_response'],
            ]);
            unset($attributes['last_api_response']);
            $attributes['payment_metadata'] = $metadata;
        }

        $payment->forceFill($attributes)->save();
    }

    private function customerSafeMessage(Response $response, string $fallback): string
    {
        $message = $response->json('message');
        if (is_string($message) && $message !== '') {
            return $message;
        }

        return $fallback;
    }

    private function safeResponseBody(Response $response): string
    {
        return Str::limit($response->body(), 1000, '...');
    }

    private function sanitizePayload(array $payload): array
    {
        foreach (['api_key', 'api_secret', 'webhook_secret', 'client_secret'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }

    private function withoutChecksumFields(array $payload): array
    {
        unset($payload['checksum'], $payload['checksumMethod']);
        return $payload;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
