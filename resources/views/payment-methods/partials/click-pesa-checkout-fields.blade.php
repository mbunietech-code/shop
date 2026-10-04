@php
    $clickPesaMethods = \App\Services\Payment\ClickPesaService::paymentMethods();
    $gatewayValues = ($payment_gateway->mode ?? 'live') === 'test'
        ? ($payment_gateway->test_values ?? [])
        : ($payment_gateway->live_values ?? []);
    $enabledMethods = $gatewayValues['enabled_methods'] ?? \App\Services\Payment\ClickPesaService::defaultEnabledMethods();
    if (is_string($enabledMethods)) {
        $decodedMethods = json_decode($enabledMethods, true);
        $enabledMethods = json_last_error() === JSON_ERROR_NONE ? $decodedMethods : explode(',', $enabledMethods);
    }
    $enabledMethods = array_values(array_filter((array) $enabledMethods));
@endphp

<div class="clickpesa-payment-options">
    <div class="mb-3">
        <label class="form-label small d-block">{{ translate('payment_method') }}</label>
        <div class="row g-2">
            @foreach($enabledMethods as $methodKey)
                @if(isset($clickPesaMethods[$methodKey]))
                    <div class="col-sm-6">
                        <label class="border rounded d-flex align-items-center gap-2 p-2 mb-0 cursor-pointer h-100">
                            <input type="radio"
                                   name="clickpesa_method"
                                   value="{{ $methodKey }}"
                                   class="custom-radio"
                                   {{ $loop->first ? 'checked' : '' }}>
                            <span class="text-capitalize">{{ $clickPesaMethods[$methodKey]['label'] }}</span>
                        </label>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label small">{{ translate('phone_number') }} <span class="text-danger">*</span></label>
        <input type="tel"
               name="phone_number"
               class="form-control"
               placeholder="07XXXXXXXX"
               required
               pattern="^(0[67][0-9]{8}|255[67][0-9]{8}|[67][0-9]{8})$">
        <small class="text-muted">{{ translate('enter_tanzanian_phone_number') }}</small>
    </div>
</div>
