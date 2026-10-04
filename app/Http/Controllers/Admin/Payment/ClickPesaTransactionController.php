<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Models\ClickPesaWebhookLog;
use App\Models\PaymentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClickPesaTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('searchValue');
        $status = $request->input('status');

        $transactions = PaymentRequest::query()
            ->where(function ($query) {
                $query->where('payment_method', 'click_pesa')
                    ->orWhereNotNull('provider_reference')
                    ->orWhereNotNull('control_number');
            })
            ->when($status && $status !== 'all', function ($query) use ($status) {
                $query->where('payment_status', $status);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")
                        ->orWhere('payment_phone', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%")
                        ->orWhere('provider_transaction_id', 'like', "%{$search}%")
                        ->orWhere('provider_reference', 'like', "%{$search}%")
                        ->orWhere('control_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->appends($request->query());

        return view('admin-views.payment.click-pesa-transactions.index', [
            'transactions' => $transactions,
            'search' => $search,
            'status' => $status ?: 'all',
        ]);
    }

    public function show(string $id): View
    {
        $transaction = PaymentRequest::where('id', $id)->firstOrFail();
        $webhookLogs = ClickPesaWebhookLog::where('payment_request_id', $id)
            ->orWhere('provider_reference', $transaction->provider_reference)
            ->orWhere('provider_reference', $transaction->control_number)
            ->latest()
            ->paginate(15);

        return view('admin-views.payment.click-pesa-transactions.show', [
            'transaction' => $transaction,
            'webhookLogs' => $webhookLogs,
        ]);
    }
}
