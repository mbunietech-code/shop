@php use Illuminate\Support\Facades\Session; @endphp
@extends('layouts.back-end.app')
@section('title', translate('payment_Method'))
@section('content')
    @php($direction = Session::get('direction') === "rtl" ? 'right' : 'left')
    <div class="content container-fluid">
        <div class="mb-4 pb-2">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img src="{{dynamicAsset(path: 'public/assets/back-end/img/3rd-party.png')}}" alt="">
                {{translate('3rd_party')}}
            </h2>
        </div>
        @include('admin-views.business-settings.third-party-payment-method-menu')
        @if($paymentGatewayPublishedStatus)
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body d-flex justify-content-around  align-items-center">
                        <h4 class="text-danger bg-transparent m-0">
                            <i class="tio-info-outined"></i>
                            {{ translate('your_current_payment_settings_are_disabled,because_you_have_enabled_payment_gateway_addon').' '.translate('To_visit_your_currently_active_payment_gateway_settings_please_follow_the_link').'.' }}
                        </h4>
                        <span>
                            <a href="{{!empty($paymentUrl) ? $paymentUrl : '' }}" class="btn btn-outline-primary"><i class="tio-settings mr-1"></i>{{translate('settings')}}</a>
                        </span>
                    </div>
                </div>
            </div>
        @endif
        <div class="row gy-3" id="payment-gateway-cards">
            @foreach($paymentGatewaysList as $key=> $gateway)
                <div class="col-md-6">
                    <div class="card">
                        <form action="{{route('admin.business-settings.payment-method.addon-payment-set')}}" method="POST"
                              id="{{$gateway->key_name}}-form" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <div class="card-header d-flex flex-wrap align-content-around">
                                <h5>
                                    <span class="text-uppercase">{{str_replace('_',' ',$gateway->key_name)}}</span>
                                </h5>
                                @php($additional_data = $gateway['additional_data'] != null ? json_decode($gateway['additional_data']) : [])
                                <?php
                                    if ($additional_data != null) {
                                        $img_path = $additional_data->gateway_image ? dynamicStorage(path: 'storage/app/public/payment_modules/gateway_image/'.$additional_data->gateway_image) : '';
                                    }else{
                                        $img_path = dynamicAsset(path: 'public/assets/back-end/img/modal/payment-methods/'.$gateway->key_name.'.png');
                                    }
                                ?>

                                @if(($gateway->is_active == 0 && $gateway->is_enabled_to_use) || ($gateway->is_active == 1))
                                    <label class="switcher show-status-text">
                                        <input class="switcher_input toggle-switch-dynamic-image" type="checkbox" name="status" value="1"
                                               id="{{$gateway->key_name}}" {{$gateway['is_active'] == 1?'checked':''}}
                                               data-modal-id = "toggle-modal"
                                               data-toggle-id = "{{$gateway->key_name}}"
                                               data-on-image = "{{ $img_path }}"
                                               data-off-image = "{{ $img_path }}"
                                               data-on-title = "{{translate('want_to_Turn_ON_')}}{{str_replace('_',' ',strtoupper($gateway->key_name))}}{{translate('_as_the_Digital_Payment_method').'?'}}"
                                               data-off-title = "{{translate('want_to_Turn_OFF_')}}{{str_replace('_',' ',strtoupper($gateway->key_name))}}{{translate('_as_the_Digital_Payment_method').'?'}}"
                                               data-on-message = "<p>{{translate('if_enabled_customers_can_use_this_payment_method')}}</p>"
                                               data-off-message = "<p>{{translate('if_disabled_this_payment_method_will_be_hidden_from_the_checkout_page')}}</p>">
                                        <span class="switcher_control" data-ontitle="{{ translate('on') }}" data-offtitle="{{ translate('off') }}"></span>
                                    </label>
                                @else
                                    <label class="switcher" data-toggle="modal" data-target="#gateway-modal-{{ $gateway['key_name'] }}">
                                        <input class="switcher_input" type="checkbox" name="status" value="1" disabled
                                               id="{{$gateway->key_name}}" {{$gateway['is_active'] == 1?'checked':''}}
                                               data-modal-id = "toggle-modal"
                                               data-toggle-id = "{{$gateway->key_name}}"
                                               data-on-image = "{{ $img_path }}"
                                               data-off-image = "{{ $img_path }}"
                                               data-on-title = "{{translate('want_to_Turn_ON_')}}{{str_replace('_',' ',strtoupper($gateway->key_name))}}{{translate('_as_the_Digital_Payment_method').'?'}}"
                                               data-off-title = "{{translate('want_to_Turn_OFF_')}}{{str_replace('_',' ',strtoupper($gateway->key_name))}}{{translate('_as_the_Digital_Payment_method').'?'}}"
                                               data-on-message = "<p>{{translate('if_enabled_customers_can_use_this_payment_method')}}</p>"
                                               data-off-message = "<p>{{translate('if_disabled_this_payment_method_will_be_hidden_from_the_checkout_page')}}</p>">
                                        <span class="switcher_control" data-ontitle="{{ translate('on') }}" data-offtitle="{{ translate('off') }}"></span>
                                    </label>
                                @endif
                            </div>
                            <div class="card-body">
                                <div class="payment--gateway-img">
                                    <img class="height-80px" id="gateway-image-{{$gateway->key_name}}"
                                         src="{{ getValidImage(path:'storage/app/public/payment_modules/gateway_image/'.($additional_data->gateway_image ?? ''), type: 'backend-payment' ) }}"
                                         alt="{{translate('public')}}">
                                </div>
                                <input name="gateway" value="{{$gateway->key_name}}" class="d-none">
                                @php($mode = $gateway->live_values['mode'])
                                <div class="form-group mb-10px" >
                                    <select class="js-example-responsive form-control" name="mode">
                                        <option value="live" {{$mode=='live'?'selected':''}}>{{translate('live')}}</option>
                                        <option value="test" {{$mode=='test'?'selected':''}}>{{translate('test')}}</option>
                                    </select>
                                </div>
                                @if($gateway->key_name === 'click_pesa')
                                    @php($clickPesaValues = $gateway->live_values ?? [])
                                    @php($clickPesaMethods = \App\Services\Payment\ClickPesaService::paymentMethods())
                                    @php($enabledMethods = $clickPesaValues['enabled_methods'] ?? \App\Services\Payment\ClickPesaService::defaultEnabledMethods())
                                    @if(is_string($enabledMethods))
                                        @php($decodedMethods = json_decode($enabledMethods, true))
                                        @php($enabledMethods = json_last_error() === JSON_ERROR_NONE ? $decodedMethods : explode(',', $enabledMethods))
                                    @endif
                                    <div class="form-group mb-10px">
                                        <label class="form-label">Client ID <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="client_id"
                                               placeholder="{{ \App\Services\Payment\ClickPesaService::decryptCredential($clickPesaValues['client_id'] ?? '') ? translate('leave_blank_to_keep_existing') : 'Client ID *' }}">
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="form-label">API Key <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="api_key"
                                               placeholder="{{ \App\Services\Payment\ClickPesaService::decryptCredential($clickPesaValues['api_key'] ?? '') ? translate('leave_blank_to_keep_existing') : 'API Key *' }}">
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="form-label">API Secret / Checksum Key</label>
                                        <input type="password" class="form-control" name="api_secret"
                                               placeholder="{{ \App\Services\Payment\ClickPesaService::decryptCredential($clickPesaValues['api_secret'] ?? '') ? translate('leave_blank_to_keep_existing') : 'API Secret' }}">
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="form-label">Webhook Secret</label>
                                        <input type="password" class="form-control" name="webhook_secret"
                                               placeholder="{{ \App\Services\Payment\ClickPesaService::decryptCredential($clickPesaValues['webhook_secret'] ?? '') ? translate('leave_blank_to_keep_existing') : 'Webhook Secret' }}">
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="form-label">Webhook URL</label>
                                        <input type="text" class="form-control" readonly value="{{ url('/api/payments/clickpesa/webhook') }}">
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="form-label">BillPay Mode</label>
                                        <select class="form-control" name="bill_payment_mode">
                                            <option value="EXACT" {{ ($clickPesaValues['bill_payment_mode'] ?? 'EXACT') === 'EXACT' ? 'selected' : '' }}>Exact amount</option>
                                            <option value="ALLOW_PARTIAL_AND_OVER_PAYMENT" {{ ($clickPesaValues['bill_payment_mode'] ?? '') === 'ALLOW_PARTIAL_AND_OVER_PAYMENT' ? 'selected' : '' }}>Allow partial and over payment</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="form-label d-block">Payment methods shown at checkout</label>
                                        <div class="row">
                                            @foreach($clickPesaMethods as $methodKey => $method)
                                                <div class="col-sm-6 mb-2">
                                                    <label class="d-flex align-items-center gap-2 mb-0">
                                                        <input type="checkbox" name="enabled_methods[]" value="{{ $methodKey }}"
                                                            {{ in_array($methodKey, (array) $enabledMethods, true) ? 'checked' : '' }}>
                                                        <span>{{ $method['label'] }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="form-group mb-10px">
                                        <label class="d-flex align-items-center gap-2">
                                            <input type="checkbox" name="checksum_enabled" value="1" {{ (int)($clickPesaValues['checksum_enabled'] ?? 0) === 1 ? 'checked' : '' }}>
                                            <span>Enable checksum signing/verification</span>
                                        </label>
                                    </div>
                                    <div class="form-group mb-10px">
                                        <button type="button" class="btn btn-outline-primary btn-sm clickpesa-test-connection"
                                                data-url="{{ route('admin.business-settings.payment-method.clickpesa-test-connection') }}">
                                            Test Connection
                                        </button>
                                        <a class="btn btn-outline-secondary btn-sm"
                                           href="{{ route('admin.business-settings.payment-transactions.clickpesa.index') }}">
                                            View Transactions
                                        </a>
                                        <small class="d-block mt-2 text-muted clickpesa-test-result"></small>
                                    </div>
                                @else
                                    @if($gateway->key_name === 'paystack')
                                        @php($skip=['gateway','mode','status','callback_url'])
                                    @else
                                        @php($skip=['gateway','mode','status'])
                                    @endif
                                    @foreach($gateway->live_values as $gatewayKey => $value)
                                        @if(!in_array($gatewayKey , $skip))
                                            <div class="form-group mb-10px">
                                                <label for="exampleFormControlInput1"
                                                       class="form-label">{{ucwords(str_replace('_',' ',$gatewayKey))}}
                                                       <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                       name="{{$gatewayKey}}"
                                                       placeholder="{{ucwords(str_replace('_',' ',$gatewayKey))}} *"
                                                       value="{{env('APP_ENV')=='demo'?'':$value}}">
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                                <div class="form-group mb-10px" >
                                    <label for="exampleFormControlInput1"
                                           class="form-label">{{translate('payment_gateway_title')}} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control"
                                           name="gateway_title"
                                           placeholder="{{translate('payment_gateway_title')}}"
                                           value="{{$additional_data != null ? $additional_data->gateway_title : ''}}" required>
                                </div>

                                <div class="form-group mb-10px" >
                                    <label for="exampleFormControlInput1"
                                           class="form-label text-capitalize">{{translate('choose_logo')}} </label>
                                    <input type="file" class="form-control image-input" name="gateway_image" accept=".jpg, .png, .jpeg|image/*" data-image-id="gateway-image-{{$gateway->key_name}}" >
                                </div>
                                <div class="text-right mb-20px">
                                    <button type="submit" class="btn btn-primary px-5">{{translate('save')}}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @if(!(($gateway->is_active == 0 && $gateway->is_enabled_to_use) || ($gateway->is_active == 1 && $gateway->must_required_for_currency != 1)))
                    <div class="modal fade" id="gateway-modal-{{ $gateway['key_name'] }}" tabindex="-1" aria-labelledby="toggle-modal" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content shadow-lg">
                                <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                                    <button type="button" class="btn-close border-0" data-dismiss="modal" aria-label="Close">
                                        <i class="tio-clear"></i>
                                    </button>
                                </div>
                                <div class="modal-body px-4 px-sm-5 pt-0">
                                    <div class="d-flex flex-column align-items-center text-center gap-2 mb-2">
                                        <div class="toggle-modal-img-box d-flex flex-column justify-content-center align-items-center mb-3 position-relative">
                                            @if($gateway['is_active'] == 1)
                                                <img src="{{ getValidImage(path: 'payment-gateway-off.png', type: 'banner', source: asset('public/assets/back-end/img/modal/payment-gateway-off.png')) }}" class="status-icon"  alt="" width="80"/>
                                            @else
                                                <img src="{{ getValidImage(path: 'payment-gateway-on.png', type: 'banner', source: asset('public/assets/back-end/img/modal/payment-gateway-on.png')) }}" class="status-icon"  alt="" width="80"/>
                                            @endif
                                            <img src="" alt="" />
                                        </div>
                                        <h5 class="modal-title">
                                            {{ translate('Are_you_sure') }}, {{ translate('want_to_turn_'. ($gateway['is_active'] == 1 ? 'Off' : 'ON').'_'.$gateway['key_name'] .'_as_the_Digital_Payment_method') }}?
                                        </h5>
                                        <div class="text-center">
                                            {{ translate('If_you_enable_this_payment_gateway_please_check_in_currency_settings_that_currency_support_this_gateway_or_not') }}!
                                        </div>
                                        <div class="text-center py-3">
                                            <a class="text-underline font-weight-bold" href="{{ route('admin.currency.view') }}">
                                                {{ translate('Go_to_currency_settings') }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-center gap-3 mt-3">
                                        <button type="button" class="btn btn--primary min-w-120" data-dismiss="modal">
                                            {{ translate('ok') }}
                                        </button>
                                        <button type="button" class="btn btn-danger-light min-w-120" data-dismiss="modal">
                                            {{ translate('cancel') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

        </div>
    </div>
@endsection

@push('script')
    <script>
        'use strict';
        @if($paymentGatewayPublishedStatus)
            let paymentGatewayCards = $('#payment-gateway-cards');
            paymentGatewayCards.find('input').each(function () {
                $(this).attr('disabled', true);
            });
            paymentGatewayCards.find('select').each(function () {
                $(this).attr('disabled', true);
            });
            paymentGatewayCards.find('.switcher_input').each(function () {
                $(this).removeAttr('checked', true);
            });
            paymentGatewayCards.find('button').each(function () {
                $(this).attr('disabled', true);
            });
        @endif

        $('.clickpesa-test-connection').on('click', function () {
            let button = $(this);
            let result = button.closest('.form-group').find('.clickpesa-test-result');
            result.removeClass('text-success text-danger').text('{{ translate('testing') }}...');

            $.ajax({
                url: button.data('url'),
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    result.addClass('text-success').text(response.message);
                },
                error: function (xhr) {
                    result.addClass('text-danger').text(xhr.responseJSON?.message || 'ClickPesa connection failed');
                }
            });
        });
    </script>
@endpush
