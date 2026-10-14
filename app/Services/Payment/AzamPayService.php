<?php

namespace App\Services\Payment;

use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * AzamPay Checkout API (https://developerdocs.azampay.co.tz).
 *
 * Payments are only ever marked as paid after AzamPay's transaction status API
 * confirms them. Browser redirects and checkout callbacks are only hints: the
 * callback supplies AzamPay's reference and operator, which are then verified
 * server-side before anything is recorded.
 */
class AzamPayService
{
    public const GATEWAY = 'azam_pay';

    public const STATUS_PAID = 'PAID';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_REVIEW = 'REVIEW_REQUIRED';

    private const LIVE_BASE_URL = 'https://checkout.azampay.co.tz';
    private const LIVE_AUTH_URL = 'https://authenticator.azampay.co.tz';
    private const TEST_BASE_URL = 'https://sandbox.azampay.co.tz';
    private const TEST_AUTH_URL = 'https://authenticator-sandbox.azampay.co.tz';

    /**
     * AzamPay limits externalId to 30 characters.
     */
    public static function externalIdFor(PaymentRequest $payment): string
    {
        return substr((string) $payment->id, 0, 30);
    }

    public function config(): array
    {
        $setting = DB::table('addon_settings')
            ->where('key_name', self::GATEWAY)
            ->where('settings_type', 'payment_config')
            ->first();

        if (!$setting) {
            throw new RuntimeException('AzamPay is not configured.');
        }

        $mode = $setting->mode === 'live' ? 'live' : 'test';
        $values = json_decode($mode === 'live' ? $setting->live_values : $setting->test_values, true) ?: [];

        $config = [
            'mode' => $mode,
            'base_url' => $mode === 'live' ? self::LIVE_BASE_URL : self::TEST_BASE_URL,
            'auth_url' => $mode === 'live' ? self::LIVE_AUTH_URL : self::TEST_AUTH_URL,
            'app_name' => trim((string) ($values['app_name'] ?? '')),
            'client_id' => trim((string) ($values['client_id'] ?? '')),
            'client_secret' => trim((string) ($values['client_secret'] ?? '')),
            'api_key' => trim((string) ($values['api_key'] ?? '')),
        ];

        if ($config['app_name'] === '' || $config['client_id'] === '' || $config['client_secret'] === '' || $config['api_key'] === '') {
            throw new RuntimeException('AzamPay credentials not configured. Please set app_name, client_id, client_secret, and api_key in admin settings.');
        }

        return $config;
    }

    /**
     * Creates the AzamPay checkout session and returns the hosted payment page URL.
     */
    public function createCheckout(PaymentRequest $payment, string $successUrl, string $failUrl): string
    {
        $config = $this->config();
        $externalId = self::externalIdFor($payment);
        $currency = strtoupper($payment->currency_code ?: 'TZS');

        $payload = [
            'appName' => $config['app_name'],
            'clientId' => $config['client_id'],
            'vendorId' => $config['client_id'],
            'language' => 'sw',
            'currency' => $currency,
            'externalId' => $externalId,
            'requestOrigin' => url('/'),
            'redirectFailURL' => $failUrl,
            'redirectSuccessURL' => $successUrl,
            'vendorName' => $config['app_name'],
            'amount' => (string) round($payment->payment_amount, 2),
            'cart' => [
                'items' => [
                    ['name' => 'Order Payment'],
                ],
            ],
        ];

        $response = $this->client($config)->post("{$config['base_url']}/api/v1/Partner/PostCheckout", $payload);
        $checkoutUrl = trim($response->body(), " \t\n\r\0\x0B\"");

        if (!$response->successful() || !filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
            Log::error('AzamPay: postcheckout failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('AzamPay checkout failed.');
        }

        $payment->forceFill([
            'payment_method' => self::GATEWAY,
            'provider_reference' => $externalId,
            'payment_currency' => $currency,
            'payment_status' => 'pending',
        ])->save();

        return $checkoutUrl;
    }

    /**
     * Handles AzamPay's checkout callback. Nothing in the payload is trusted:
     * it only tells us which AzamPay reference to verify.
     */
    public function handleCallback(array $payload): string
    {
        $externalId = (string) ($payload['utilityref'] ?? $payload['externalreference'] ?? $payload['externalId'] ?? '');
        $payment = $externalId !== '' ? $this->findByExternalId($externalId) : null;
        if (!$payment) {
            throw new RuntimeException('Payment not found.');
        }

        $reference = trim((string) ($payload['reference'] ?? ''));
        $operator = trim((string) ($payload['operator'] ?? ''));
        if ($reference === '' || $operator === '') {
            return $this->syncStatus($payment);
        }

        return $this->syncStatus($payment, [
            'reference' => $reference,
            'operator' => $operator,
            'callback_amount' => $payload['amount'] ?? null,
        ]);
    }

    /**
     * Asks AzamPay for the transaction status and records the outcome.
     * $candidate is an unverified reference reported by a callback; without it the
     * last reference stored for this payment is re-checked.
     * Returns one of the STATUS_* constants.
     */
    public function syncStatus(PaymentRequest $payment, ?array $candidate = null): string
    {
        if ((int) $payment->is_paid === 1) {
            return self::STATUS_PAID;
        }
        // A flagged payment is only re-checked when AzamPay reports a new reference,
        // so a forged callback cannot block the genuine one by triggering review.
        if ($payment->payment_status === 'review_required' && $candidate === null) {
            return self::STATUS_REVIEW;
        }

        $metadata = $this->metadata($payment);
        $candidate ??= [
            'reference' => $metadata['azampay_reference'] ?? null,
            'operator' => $metadata['azampay_operator'] ?? null,
            'callback_amount' => $metadata['azampay_callback_amount'] ?? null,
        ];
        if (empty($candidate['reference']) || empty($candidate['operator'])) {
            // AzamPay has not told us which transaction to verify yet
            return self::STATUS_PENDING;
        }

        $config = $this->config();
        $response = $this->client($config)->get("{$config['base_url']}/api/v1/azampay/transactionstatus", [
            'bankName' => $candidate['operator'],
            'pgReferenceId' => $candidate['reference'],
        ]);
        $body = $response->json();

        if (!$response->successful() || !is_array($body)) {
            Log::warning('AzamPay: transaction status query failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $result = self::STATUS_PENDING;
        } else {
            $result = $this->interpretStatus($body);
        }

        if ($result === self::STATUS_PAID) {
            return $this->markPaid($payment, $candidate, $body);
        }

        $isStored = ($metadata['azampay_reference'] ?? null) === $candidate['reference'];

        if ($result === self::STATUS_PENDING && !$isStored) {
            $this->rememberCandidate($payment, $candidate);
        } elseif ($result === self::STATUS_FAILED && $isStored) {
            // Only a reference we were already tracking may fail the payment, so a
            // forged callback carrying someone else's failed reference cannot.
            $payment->forceFill([
                'payment_status' => 'failed',
                'failed_at' => now(),
                'verification_result' => 'FAILED',
            ])->save();
        } elseif ($result === self::STATUS_FAILED) {
            return self::STATUS_PENDING;
        }

        return $result;
    }

    private function rememberCandidate(PaymentRequest $payment, array $candidate): void
    {
        DB::transaction(function () use ($payment, $candidate): void {
            $locked = PaymentRequest::where('id', $payment->id)->lockForUpdate()->first();
            if (!$locked || (int) $locked->is_paid === 1) {
                return;
            }

            $metadata = $this->metadata($locked);
            $metadata['azampay_reference'] = $candidate['reference'];
            $metadata['azampay_operator'] = $candidate['operator'];
            $metadata['azampay_callback_amount'] = $candidate['callback_amount'];
            $locked->forceFill(['payment_metadata' => $metadata])->save();
        }, 3);
    }

    /**
     * Marks the payment as paid and runs its success hook exactly once.
     */
    private function markPaid(PaymentRequest $payment, array $candidate, array $statusBody): string
    {
        return DB::transaction(function () use ($payment, $candidate, $statusBody): string {
            $reference = (string) $candidate['reference'];
            $locked = PaymentRequest::where('id', $payment->id)->lockForUpdate()->first();
            if (!$locked) {
                return self::STATUS_PENDING;
            }

            $metadata = $this->metadata($locked);

            if ((int) $locked->is_paid !== 1) {
                $problem = $this->verificationProblem($locked, $reference, $candidate, $statusBody);
                if ($problem !== null) {
                    Log::error('AzamPay: payment verification failed', [
                        'payment_id' => $locked->id,
                        'reference' => $reference,
                        'problem' => $problem,
                        'status_response' => $statusBody,
                    ]);
                    $locked->forceFill(['payment_status' => 'review_required', 'verification_result' => $problem])->save();
                    return self::STATUS_REVIEW;
                }

                $metadata['azampay_reference'] = $reference;
                $metadata['azampay_operator'] = $candidate['operator'];
                $locked->forceFill([
                    'is_paid' => 1,
                    'payment_method' => self::GATEWAY,
                    'payment_status' => 'paid',
                    'transaction_id' => $reference,
                    'provider_transaction_id' => $reference,
                    'provider_payment_method' => $candidate['operator'],
                    'verification_result' => 'SUCCESS',
                    'verified_at' => now(),
                    'paid_at' => now(),
                ]);
            }

            $hook = $locked->success_hook;
            if (empty($metadata['success_hook_called_at']) && is_string($hook) && function_exists($hook)) {
                call_user_func($hook, $locked);
                $metadata['success_hook_called_at'] = now()->toDateTimeString();
            }

            $locked->forceFill(['payment_metadata' => $metadata])->save();

            return self::STATUS_PAID;
        }, 3);
    }

    /**
     * Runs the failure hook exactly once, and never for a paid payment.
     */
    public function runFailureHook(PaymentRequest $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = PaymentRequest::where('id', $payment->id)->lockForUpdate()->first();
            if (!$locked || (int) $locked->is_paid === 1) {
                return;
            }

            $metadata = $this->metadata($locked);
            $hook = $locked->failure_hook;
            if (empty($metadata['failure_hook_called_at']) && is_string($hook) && function_exists($hook)) {
                call_user_func($hook, $locked);
                $metadata['failure_hook_called_at'] = now()->toDateTimeString();
                $locked->forceFill(['payment_metadata' => $metadata])->save();
            }
        }, 3);
    }

    public function findByExternalId(string $externalId): ?PaymentRequest
    {
        $payment = PaymentRequest::where('provider_reference', $externalId)
            ->where('payment_method', self::GATEWAY)
            ->first();

        // Checkouts started before provider_reference was recorded: externalId is the id prefix
        if (!$payment && preg_match('/^[0-9a-f-]{30}$/i', $externalId)) {
            $payment = PaymentRequest::where('id', 'like', $externalId . '%')->first();
        }

        return $payment;
    }

    /**
     * Maps AzamPay's transaction status response to PAID / FAILED / PENDING.
     * Response shape: {"data": <string|object>, "message": string, "success": bool, "statusCode": int}
     */
    private function interpretStatus(array $body): string
    {
        $data = $body['data'] ?? null;
        $statusText = strtolower(trim(is_array($data)
            ? (string) ($data['transactionStatus'] ?? $data['transactionstatus'] ?? $data['status'] ?? '')
            : (string) $data));

        if (($body['success'] ?? false) !== true) {
            return str_contains($statusText, 'fail') ? self::STATUS_FAILED : self::STATUS_PENDING;
        }

        if (str_contains($statusText, 'fail') || str_contains($statusText, 'reject') || str_contains($statusText, 'cancel')) {
            return self::STATUS_FAILED;
        }

        if (str_contains($statusText, 'success') || $statusText === 'completed') {
            return self::STATUS_PAID;
        }

        return self::STATUS_PENDING;
    }

    /**
     * Cross-checks the confirmed transaction against this payment.
     * Returns null when everything matches, otherwise a short reason.
     */
    private function verificationProblem(PaymentRequest $payment, string $reference, array $candidate, array $statusBody): ?string
    {
        // One AzamPay transaction can only ever pay for one payment request
        $alreadyUsed = PaymentRequest::where('provider_transaction_id', $reference)
            ->where('payment_method', self::GATEWAY)
            ->where('id', '!=', $payment->id)
            ->exists();
        if ($alreadyUsed) {
            return 'REFERENCE_REUSED';
        }

        $data = is_array($statusBody['data'] ?? null) ? $statusBody['data'] : [];

        // Prefer the externalId and amount AzamPay reports; fall back to the callback amount
        $reportedExternalId = $data['utilityref'] ?? $data['externalId'] ?? $data['externalReference'] ?? $data['externalreference'] ?? null;
        if ($reportedExternalId !== null && (string) $reportedExternalId !== self::externalIdFor($payment)) {
            return 'EXTERNAL_ID_MISMATCH';
        }

        $amount = $data['amount'] ?? $candidate['callback_amount'] ?? null;
        if ($amount === null || !is_numeric($amount)) {
            return 'AMOUNT_MISSING';
        }
        if ((float) $amount + 0.01 < (float) $payment->payment_amount) {
            return 'AMOUNT_MISMATCH';
        }

        return null;
    }

    private function client(array $config): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(30)->acceptJson()->withHeaders([
            'Authorization' => 'Bearer ' . $this->token($config),
            'X-API-Key' => $config['api_key'],
        ]);
    }

    private function token(array $config): string
    {
        $response = Http::timeout(30)->acceptJson()->post("{$config['auth_url']}/AppRegistration/GenerateToken", [
            'appName' => $config['app_name'],
            'clientId' => $config['client_id'],
            'clientSecret' => $config['client_secret'],
        ]);

        $token = $response->json('data.accessToken');
        if (!$response->successful() || !is_string($token) || $token === '') {
            Log::error('AzamPay: token request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Failed to authenticate with AzamPay. Check your API credentials.');
        }

        return $token;
    }

    private function metadata(PaymentRequest $payment): array
    {
        $metadata = $payment->payment_metadata;
        if (is_array($metadata)) {
            return $metadata;
        }

        return json_decode((string) $metadata, true) ?: [];
    }
}
