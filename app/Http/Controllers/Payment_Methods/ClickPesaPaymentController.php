<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Services\Payment\ClickPesaService;
use App\Traits\Processor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClickPesaPaymentController extends Controller
{
    use Processor;

    public function __construct(
        private readonly PaymentRequest $payment,
        private readonly ClickPesaService $clickPesaService,
    ) {
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment = $this->payment::where(['id' => $request['payment_id']])
            ->where(['is_paid' => 0])
            ->first();

        if (!$payment) {
            Log::error('ClickPesa: payment_id not found or already paid', ['payment_id' => $request['payment_id']]);
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $additionalData = json_decode($payment->additional_data ?: '{}', true) ?: [];
        $phoneNumber = $additionalData['phone_number'] ?? null;
        $selectedMethod = $additionalData['clickpesa_method'] ?? 'airtel_money';
        $methods = ClickPesaService::paymentMethods();
        $selectedType = $methods[$selectedMethod]['type'] ?? 'mobile_money';

        if (in_array($payment->payment_status, ['failed', 'expired', 'cancelled'], true)) {
            return response()->view('payment-methods.click-pesa-failed', [
                'message' => translate('payment_request_failed'),
                'payment_id' => $payment->id,
            ], 422);
        }

        try {
            if ($selectedType === 'billpay') {
                if (!empty($payment->control_number)) {
                    return view('payment-methods.click-pesa-control-number', [
                        'payment' => $payment,
                        'payment_id' => $payment->id,
                        'control_number' => $payment->control_number,
                        'amount' => $payment->payment_amount,
                        'currency' => $payment->currency_code,
                        'phone' => $payment->payment_phone,
                        'status' => $payment->payment_status ?: 'pending',
                        'status_route' => route('click-pesa.status', ['payment_id' => $payment->id]),
                    ]);
                }

                $result = $this->clickPesaService->createControlNumber($payment, $phoneNumber, $selectedMethod);
                $payment = $payment->fresh();

                return view('payment-methods.click-pesa-control-number', [
                    'payment' => $payment,
                    'payment_id' => $payment->id,
                    'control_number' => $result['billPayNumber'] ?? $payment->control_number,
                    'amount' => $payment->payment_amount,
                    'currency' => $payment->currency_code,
                    'phone' => $payment->payment_phone,
                    'status' => $payment->payment_status ?: 'pending',
                    'status_route' => route('click-pesa.status', ['payment_id' => $payment->id]),
                ]);
            }

            if (!empty($payment->provider_reference)) {
                return view('payment-methods.click-pesa-waiting', [
                    'payment' => $payment,
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->provider_transaction_id,
                    'status' => $payment->payment_status ?: 'PROCESSING',
                    'amount' => $payment->payment_amount,
                    'currency' => $payment->currency_code,
                    'phone' => $payment->payment_phone,
                    'method_label' => $methods[$selectedMethod]['label'] ?? 'Mobile Money',
                    'status_route' => route('click-pesa.status', ['payment_id' => $payment->id]),
                ]);
            }

            $result = $this->clickPesaService->initiateUssdPush($payment, $phoneNumber, $selectedMethod);
            $payment = $payment->fresh();

            return view('payment-methods.click-pesa-waiting', [
                'payment' => $payment,
                'payment_id' => $payment->id,
                'transaction_id' => $result['id'] ?? $payment->provider_transaction_id,
                'status' => $result['status'] ?? $payment->payment_status ?? 'PROCESSING',
                'amount' => $payment->payment_amount,
                'currency' => $payment->currency_code,
                'phone' => $payment->payment_phone,
                'method_label' => $methods[$selectedMethod]['label'] ?? 'Mobile Money',
                'status_route' => route('click-pesa.status', ['payment_id' => $payment->id]),
            ]);
        } catch (\Throwable $exception) {
            Log::error('ClickPesa: payment initialization failed', [
                'payment_id' => $payment->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->view('payment-methods.click-pesa-failed', [
                'message' => $exception->getMessage(),
                'payment_id' => $payment->id,
            ], 422);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        if ($request->ajax() || $request->has('payment_id')) {
            return $this->queryStatus($request);
        }

        return $this->handleWebhook($request);
    }

    public function webhook(Request $request): JsonResponse
    {
        return $this->handleWebhook($request);
    }

    public function queryStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST'], 422);
        }

        $payment = $this->payment::where(['id' => $request['payment_id']])->first();
        if (!$payment) {
            return response()->json(['status' => 'NOT_FOUND'], 404);
        }

        $wasPaid = (int) $payment->is_paid === 1;

        if (!$wasPaid && !empty($payment->provider_reference)) {
            try {
                $this->clickPesaService->queryPayment($payment);
                $payment = $payment->fresh();
            } catch (\Throwable $exception) {
                Log::warning('ClickPesa: payment status query failed', [
                    'payment_id' => $payment->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ((int) $payment->is_paid === 1) {
            $this->runSuccessHook($payment);

            return response()->json([
                'status' => 'SUCCESS',
                'redirect' => $this->successRedirect($payment),
            ]);
        }

        if (in_array($payment->payment_status, ['failed', 'expired', 'cancelled'], true)) {
            $this->runFailureHook($payment);

            return response()->json([
                'status' => strtoupper($payment->payment_status),
                'redirect' => $this->failRedirect($payment),
            ]);
        }

        if ($payment->payment_status === 'review_required') {
            return response()->json([
                'status' => 'REVIEW_REQUIRED',
                'message' => translate('payment_requires_admin_review'),
            ]);
        }

        return response()->json([
            'status' => strtoupper($payment->payment_status ?: 'PROCESSING'),
            'control_number' => $payment->control_number,
            'transaction_id' => $payment->provider_transaction_id,
        ]);
    }

    private function handleWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->clickPesaService->processWebhook(
                $request->all(),
                $request->headers->all(),
            );

            $payment = $result['payment'] ?? null;
            if ($payment instanceof PaymentRequest && (int) $payment->is_paid === 1) {
                $this->runSuccessHook($payment);
            }

            return response()->json(['message' => 'received']);
        } catch (\Throwable $exception) {
            Log::error('ClickPesa webhook failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'webhook failed'], 422);
        }
    }

    private function runSuccessHook(PaymentRequest $payment): void
    {
        $this->runPaymentHookOnce($payment, 'success_hook', 'success_hook_called_at');
    }

    private function runFailureHook(PaymentRequest $payment): void
    {
        $this->runPaymentHookOnce($payment, 'failure_hook', 'failure_hook_called_at');
    }

    private function runPaymentHookOnce(PaymentRequest $payment, string $hookColumn, string $metadataKey): void
    {
        $hook = $payment->{$hookColumn};
        if (!is_string($hook) || !function_exists($hook)) {
            return;
        }

        DB::transaction(function () use ($payment, $hookColumn, $metadataKey): void {
            $lockedPayment = PaymentRequest::where('id', $payment->id)->lockForUpdate()->first();
            if (!$lockedPayment) {
                return;
            }

            $metadata = $this->paymentMetadata($lockedPayment);
            if (!empty($metadata[$metadataKey])) {
                return;
            }

            call_user_func($lockedPayment->{$hookColumn}, $lockedPayment);

            $metadata[$metadataKey] = now()->toDateTimeString();
            $lockedPayment->forceFill(['payment_metadata' => $metadata])->save();
        }, 3);
    }

    private function paymentMetadata(PaymentRequest $payment): array
    {
        if (empty($payment->payment_metadata)) {
            return [];
        }

        return is_array($payment->payment_metadata)
            ? $payment->payment_metadata
            : (json_decode($payment->payment_metadata, true) ?: []);
    }

    private function successRedirect(PaymentRequest $payment): string
    {
        $response = $this->payment_response($payment, 'success');

        return method_exists($response, 'getTargetUrl') ? $response->getTargetUrl() : url('/');
    }

    private function failRedirect(PaymentRequest $payment): string
    {
        $response = $this->payment_response($payment, 'fail');

        return method_exists($response, 'getTargetUrl') ? $response->getTargetUrl() : url('/');
    }
}
