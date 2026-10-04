@extends('layouts.front-end.app')
@section('title', translate('Control_Number'))
@section('content')
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    <h3 class="mb-3">{{ translate('PAYMENT_CONTROL_NUMBER') }}</h3>
                    <p class="text-muted mb-4">
                        {{ translate('Use_the_Control_Number_to_pay_through_supported_mobile_money_or_CRDB_channels') }}.
                    </p>

                    <div class="border rounded p-4 mb-3">
                        <p class="text-muted mb-1">{{ translate('control_number') }}</p>
                        <h2 class="mb-3" id="clickpesa-control-number">{{ $control_number }}</h2>
                        <button type="button" class="btn btn-outline-primary" id="copy-control-number">
                            {{ translate('Copy_Control_Number') }}
                        </button>
                    </div>

                    <div class="text-left border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('amount') }}</span>
                            <strong>{{ $currency ?? 'TZS' }} {{ number_format((float)($amount ?? 0), 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('phone_number') }}</span>
                            <strong>{{ $phone ?? '' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('Payment_Status') }}</span>
                            <strong id="payment-status-text">{{ strtoupper($status ?? 'pending') }}</strong>
                        </div>
                    </div>

                    <div id="payment-status" class="mt-3">
                        <p class="text-muted">{{ translate('Waiting_for_payment_confirmation') }}...</p>
                    </div>
                    <button type="button" class="btn btn-primary mt-2" id="refresh-clickpesa-status">
                        {{ translate('Refresh_Status') }}
                    </button>
                    <a href="{{ route('checkout-payment') }}" class="btn btn-outline-primary mt-2">
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
        const statusUrl = '{{ $status_route ?? route("click-pesa.status", ["payment_id" => $payment_id]) }}';
        const statusText = document.getElementById('payment-status-text');
        const statusBox = document.getElementById('payment-status');

        function checkStatus() {
            fetch(statusUrl, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                statusText.textContent = data.status || 'PENDING';
                if (data.status === 'SUCCESS') {
                    statusBox.innerHTML = '<p class="text-success">{{ translate("Payment_success") }}</p>';
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else if (data.status === 'FAILED') {
                    statusBox.innerHTML = '<p class="text-danger">{{ translate("Payment_failed") }}</p>';
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else if (data.status === 'REVIEW_REQUIRED') {
                    statusBox.innerHTML = '<p class="text-warning">{{ translate("payment_requires_admin_review") }}</p>';
                } else {
                    statusBox.innerHTML = '<p class="text-muted">{{ translate("Waiting_for_payment_confirmation") }}...</p>';
                }
            })
            .catch(() => {});
        }

        document.getElementById('refresh-clickpesa-status').addEventListener('click', checkStatus);
        document.getElementById('copy-control-number').addEventListener('click', function() {
            navigator.clipboard.writeText(document.getElementById('clickpesa-control-number').textContent.trim());
        });

        setInterval(checkStatus, 10000);
    })();
</script>
@endpush
