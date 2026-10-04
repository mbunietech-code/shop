@extends('layouts.back-end.app')
@section('title', 'ClickPesa Transaction')

@section('content')
    @php($payer = json_decode($transaction->payer_information ?: '{}', true) ?: [])
    @php($metadata = is_array($transaction->payment_metadata) ? $transaction->payment_metadata : (json_decode($transaction->payment_metadata ?: '{}', true) ?: []))

    <div class="content container-fluid">
        <div class="mb-4 pb-2 d-flex justify-content-between align-items-center">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/3rd-party.png') }}" alt="">
                ClickPesa Transaction
            </h2>
            <a class="btn btn-outline-primary" href="{{ route('admin.business-settings.payment-transactions.clickpesa.index') }}">
                {{ translate('back') }}
            </a>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Details</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><td>ID</td><td><strong>{{ $transaction->id }}</strong></td></tr>
                            <tr><td>Customer</td><td>{{ $payer['name'] ?? '-' }}</td></tr>
                            <tr><td>Email</td><td>{{ $payer['email'] ?? '-' }}</td></tr>
                            <tr><td>Phone</td><td>{{ $transaction->payment_phone ?? ($payer['phone'] ?? '-') }}</td></tr>
                            <tr><td>Amount</td><td>{{ $transaction->currency_code }} {{ number_format((float)$transaction->payment_amount, 2) }}</td></tr>
                            <tr><td>Provider</td><td>ClickPesa</td></tr>
                            <tr><td>Method</td><td>{{ $transaction->provider_payment_method ?? '-' }}</td></tr>
                            <tr><td>Reference</td><td>{{ $transaction->provider_reference ?? '-' }}</td></tr>
                            <tr><td>Transaction ID</td><td>{{ $transaction->provider_transaction_id ?? $transaction->transaction_id ?? '-' }}</td></tr>
                            <tr><td>Control Number</td><td>{{ $transaction->control_number ?? '-' }}</td></tr>
                            <tr><td>Status</td><td>{{ ucwords(str_replace('_', ' ', $transaction->payment_status ?? '-')) }}</td></tr>
                            <tr><td>Created</td><td>{{ optional($transaction->created_at)->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><td>Paid At</td><td>{{ optional($transaction->paid_at)->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                            <tr><td>Verification</td><td>{{ $transaction->verification_result ?? '-' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Verification / API Data</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light rounded p-3 mb-0" style="white-space: pre-wrap;">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Webhook Events</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>Event</th>
                            <th>Reference</th>
                            <th>Transaction</th>
                            <th>Status</th>
                            <th>Received</th>
                            <th>Processed</th>
                            <th>Error</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($webhookLogs as $log)
                            <tr>
                                <td>{{ $log->event }}</td>
                                <td>{{ $log->provider_reference ?? '-' }}</td>
                                <td>{{ $log->provider_transaction_id ?? '-' }}</td>
                                <td>{{ $log->processing_status }}</td>
                                <td>{{ optional($log->received_at)->format('Y-m-d H:i:s') }}</td>
                                <td>{{ optional($log->processed_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>{{ $log->error_message ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">{{ translate('no_data_found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end">
                    {{ $webhookLogs->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
