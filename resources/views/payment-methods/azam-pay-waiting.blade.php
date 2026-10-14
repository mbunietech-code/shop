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
                    <p class="text-muted mb-4">{{ translate('Waiting_for_payment_confirmation') }}...</p>
                    <div class="text-left border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('payment_method') }}</span>
                            <strong>AzamPay</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('amount') }}</span>
                            <strong>{{ $payment->currency_code ?? 'TZS' }} {{ number_format((float) $payment->payment_amount, 2) }}</strong>
                        </div>
                    </div>
                    <div id="payment-status" class="mt-3"></div>
                    <a href="{{ route('checkout-payment') }}" class="btn btn-outline-primary mt-3">
                        {{ translate('Back_to_payment_methods') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    (function () {
        const statusBox = document.getElementById('payment-status');
        const deadline = Date.now() + 5 * 60 * 1000;
        let timer;

        function stop(message, css) {
            clearInterval(timer);
            statusBox.innerHTML = '<p class="' + css + '">' + message + '</p>';
        }

        function checkStatus() {
            if (Date.now() > deadline) {
                stop('{{ translate("Payment_is_taking_longer_than_expected") }}.', 'text-warning');
                return;
            }
            fetch('{{ $status_route }}', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(res => res.json())
                .then(data => {
                    if ((data.status === 'SUCCESS' || data.status === 'FAILED') && data.redirect) {
                        clearInterval(timer);
                        window.location.href = data.redirect;
                    } else if (data.status === 'REVIEW_REQUIRED') {
                        stop('{{ translate("payment_requires_admin_review") }}', 'text-warning');
                    }
                })
                .catch(() => {});
        }

        timer = setInterval(checkStatus, 5000);
        checkStatus();
    })();
</script>
@endpush
