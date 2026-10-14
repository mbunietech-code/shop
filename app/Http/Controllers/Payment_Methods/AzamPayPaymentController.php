<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Services\Payment\AzamPayService;
use App\Traits\Processor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AzamPayPaymentController extends Controller
{
    use Processor;

    public function __construct(
        private readonly AzamPayService $azamPayService,
    ) {
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = PaymentRequest::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json(['error' => 'Payment record not found'], 404);
        }

        try {
            return redirect()->away($this->azamPayService->createCheckout(
                $data,
                route('azam-pay.success', ['payment_id' => $data->id]),
                route('azam-pay.fail', ['payment_id' => $data->id]),
            ));
        } catch (\Throwable $exception) {
            Log::error('AzamPay: payment initialization failed', [
                'payment_id' => $data->id,
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['error' => $exception->getMessage()], 400);
        }
    }

    /**
     * Customer is sent here by AzamPay after paying. The redirect itself proves
     * nothing, so only re-check the verified status.
     */
    public function success(Request $request)
    {
        $payment = $this->findPayment($request);
        if (!$payment) {
            return redirect()->route('payment-fail');
        }

        $status = $this->refreshStatus($payment);
        $payment = $payment->fresh();

        if ($status === AzamPayService::STATUS_PAID) {
            return $this->payment_response($payment, 'success');
        }

        if ($status === AzamPayService::STATUS_FAILED) {
            $this->azamPayService->runFailureHook($payment);
            return $this->payment_response($payment, 'fail');
        }

        return view('payment-methods.azam-pay-waiting', [
            'payment' => $payment,
            'status_route' => route('azam-pay.status', ['payment_id' => $payment->id]),
        ]);
    }

    public function fail(Request $request)
    {
        $payment = $this->findPayment($request);
        if (!$payment) {
            return redirect()->route('payment-fail');
        }

        // The callback may already have confirmed payment; never fail a paid order
        if ($this->refreshStatus($payment) === AzamPayService::STATUS_PAID) {
            return $this->payment_response($payment->fresh(), 'success');
        }

        $this->azamPayService->runFailureHook($payment);
        return $this->payment_response($payment->fresh(), 'fail');
    }

    /**
     * Polled by the waiting page.
     */
    public function status(Request $request): JsonResponse
    {
        $payment = $this->findPayment($request);
        if (!$payment) {
            return response()->json(['status' => 'NOT_FOUND'], 404);
        }

        $status = $this->refreshStatus($payment);
        $payment = $payment->fresh();

        if ($status === AzamPayService::STATUS_PAID) {
            return response()->json(['status' => 'SUCCESS', 'redirect' => $this->redirectUrl($payment, 'success')]);
        }

        if ($status === AzamPayService::STATUS_FAILED) {
            $this->azamPayService->runFailureHook($payment);
            return response()->json(['status' => 'FAILED', 'redirect' => $this->redirectUrl($payment, 'fail')]);
        }

        return response()->json(['status' => $status]);
    }

    /**
     * AzamPay checkout callback. The payload only identifies which transaction
     * to verify; the outcome is fetched from AzamPay.
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $status = $this->azamPayService->handleCallback($request->all());
        } catch (\Throwable $exception) {
            Log::warning('AzamPay callback rejected', [
                'utilityref' => $request->input('utilityref'),
                'reference' => $request->input('reference'),
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Not processed'], 400);
        }

        return response()->json(['success' => true, 'status' => $status]);
    }

    private function findPayment(Request $request): ?PaymentRequest
    {
        $validator = Validator::make($request->all(), ['payment_id' => 'required|uuid']);
        if ($validator->fails()) {
            return null;
        }

        return PaymentRequest::where('id', $request['payment_id'])->first();
    }

    private function refreshStatus(PaymentRequest $payment): string
    {
        try {
            return $this->azamPayService->syncStatus($payment);
        } catch (\Throwable $exception) {
            Log::warning('AzamPay: status refresh failed', ['payment_id' => $payment->id, 'message' => $exception->getMessage()]);
            return AzamPayService::STATUS_PENDING;
        }
    }

    private function redirectUrl(PaymentRequest $payment, string $flag): string
    {
        $response = $this->payment_response($payment, $flag);

        return method_exists($response, 'getTargetUrl') ? $response->getTargetUrl() : url('/');
    }
}
