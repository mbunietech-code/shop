@extends('layouts.front-end.app')
@section('title', translate('Payment_failed'))
@section('content')
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body py-5">
                    <h3 class="mb-3">{{ translate('Payment_failed') }}</h3>
                    <p class="text-muted mb-4">
                        {{ $message ?? translate('Payment_could_not_be_started._Please_try_again_or_choose_another_payment_method') }}.
                    </p>
                    <a href="{{ route('checkout-payment') }}" class="btn btn-primary">
                        {{ translate('Back_to_payment_methods') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
