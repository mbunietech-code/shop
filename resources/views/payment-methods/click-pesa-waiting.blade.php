@extends('layouts.front-end.app')
@section('title', translate('Processing Payment'))
@section('content')
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
                            <span class="sr-only">{{ translate('Loading') }}...</span>
                        </div>
                    </div>
                    <h3 class="mb-3">{{ translate('Processing_Payment') }}</h3>
                    <p class="text-muted mb-4">
                        {{ translate('A_payment_request_has_been_sent_to_your_phone') }}.<br>
                        {{ translate('Please_check_your_phone_and_enter_your_PIN_to_complete_the_payment') }}.
                    </p>
                    <div class="text-left border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('payment_method') }}</span>
                            <strong>{{ $method_label ?? 'Mobile Money' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('amount') }}</span>
                            <strong>{{ $currency ?? 'TZS' }} {{ number_format((float)($amount ?? 0), 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('phone_number') }}</span>
                            <strong>{{ $phone ?? '' }}</strong>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <strong>{{ translate('Transaction_ID') }}:</strong> {{ $transaction_id ?? '' }}
                    </div>
                    <div id="payment-status" class="mt-3">
                        <p class="text-muted">{{ translate('Waiting_for_payment_confirmation') }}...</p>
                    </div>
                    <div id="payment-error" class="alert alert-danger d-none mt-3"></div>
                    <a href="{{ route('checkout-payment') }}" class="btn btn-outline-primary mt-3">
                        {{ translate('Cancel_and_go_back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    (function() {
        let pollInterval;
        let attempts = 0;
        const maxAttempts = 36;

        function checkStatus() {
            attempts++;
            fetch('{{ $status_route ?? route("click-pesa.status", ["payment_id" => $payment_id]) }}', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'SUCCESS') {
                    clearInterval(pollInterval);
                    window.location.href = data.redirect;
                } else if (data.status === 'FAILED') {
                    clearInterval(pollInterval);
                    document.getElementById('payment-status').innerHTML =
                        '<p class="text-danger">{{ translate("Payment_failed") }}</p>';
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else if (data.status === 'REVIEW_REQUIRED') {
                    clearInterval(pollInterval);
                    document.getElementById('payment-status').innerHTML =
                        '<p class="text-warning">{{ translate("payment_requires_admin_review") }}</p>';
                } else if (attempts >= maxAttempts) {
                    clearInterval(pollInterval);
                    document.getElementById('payment-status').innerHTML =
                        '<p class="text-warning">{{ translate("Payment_is_taking_longer_than_expected") }}.</p>';
                }
            })
            .catch(() => {});
        }

        pollInterval = setInterval(checkStatus, 5000);
        checkStatus();
        setTimeout(() => {
            clearInterval(pollInterval);
            document.getElementById('payment-status').innerHTML =
                '<p class="text-warning">{{ translate("Payment_is_taking_longer_than_expected") }}.</p>';
        }, 180000);
    })();
</script>
@endpush
