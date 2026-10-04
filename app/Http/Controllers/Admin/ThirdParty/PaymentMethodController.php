<?php

namespace App\Http\Controllers\Admin\ThirdParty;

use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Contracts\Repositories\CurrencyRepositoryInterface;
use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Enums\GlobalConstant;
use App\Enums\ViewPaths\Admin\PaymentMethod;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\PaymentMethodUpdateRequest;
use App\Services\Payment\ClickPesaService;
use App\Services\SettingService;
use App\Traits\PaymentGatewayTrait;
use App\Traits\Processor;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodController extends BaseController
{
    use Processor;
    use PaymentGatewayTrait;

    public function __construct(
        private readonly SettingRepositoryInterface         $settingRepo,
        private readonly BusinessSettingRepositoryInterface $businessSettingRepo,
        private readonly SettingService                     $settingService,
        private readonly CurrencyRepositoryInterface        $currencyRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     * Index function is the starting point of a controller
     */
    public function index(Request|null $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        return $this->getListView();
    }

    public function getListView(): View
    {
        $paymentGatewayPublishedStatus = config('get_payment_publish_status') ?? 0;
        $paymentGatewaysList = $this->settingRepo->getListWhereIn(
            whereInFilters: ['settings_type' => ['payment_config'], 'key_name' => GlobalConstant::DEFAULT_PAYMENT_GATEWAYS],
            dataLimit: 'all',
        );

        $currencies = $this->currencyRepo->getListWhere(
            dataLimit: 'all',
        );

        $paymentGatewaysList->map(function ($gateway) use ($currencies, $paymentGatewaysList) {
            $checkedData = self::checkPaymentGatewaySupportedCurrencies($gateway, $currencies, $paymentGatewaysList);
            $gateway['is_enabled_to_use'] = $checkedData['is_enabled_to_use'];
            $gateway['total_supported_currencies'] = $checkedData['total_supported_currencies'];
            $gateway['must_required_for_currency'] = $checkedData['must_required_for_currency'];
            $gateway['supported_currency'] = $checkedData['supported_currency'];
        });

        $paymentGatewaysList = $paymentGatewaysList->sortBy(function ($item) {
            return count($item['live_values']);
        })->values()->all();

        $paymentUrl = $this->settingService->getVacationData(type: 'payment_setup');
        return view(PaymentMethod::LIST[VIEW], [
            'paymentGatewaysList' => $paymentGatewaysList,
            'paymentGatewayPublishedStatus' => $paymentGatewayPublishedStatus,
            'paymentUrl' => $paymentUrl,
            'cashOnDelivery' => getWebConfig(name: 'cash_on_delivery'),
            'digitalPayment' => getWebConfig(name: 'digital_payment'),
            'offlinePayment' => getWebConfig(name: 'offline_payment'),
        ]);
    }

    function checkPaymentGatewaySupportedCurrencies($gateway, $currencyCodes, $paymentGateways): array
    {
        $getPaymentGatewaySupportedCurrencies = $this->getPaymentGatewaySupportedCurrencies(key: $gateway->key_name);
        $isEnabledToUse = 0;
        $supportForCurrency = [];
        $totalSupportedCurrencies = 0;
        $mustRequiredForCurrency = 1;
        foreach ($currencyCodes as $singleCode) {
            if ($singleCode->status == 1 && array_key_exists($singleCode->code, $getPaymentGatewaySupportedCurrencies)) {
                $isEnabledToUse = 1;
                $totalSupportedCurrencies += 1;
                $supportForCurrency[] = $singleCode->code;
            }
        }
        if (count($supportForCurrency) != 1) {
            $mustRequiredForCurrency = 0;
        }

        return [
            'is_enabled_to_use' => $isEnabledToUse,
            'total_supported_currencies' => $totalSupportedCurrencies,
            'must_required_for_currency' => $mustRequiredForCurrency,
            'supported_currency' => $supportForCurrency,
        ];
    }

    public function getPaymentOptionView():View
    {
        return view(PaymentMethod::PAYMENT_OPTION[VIEW], [
            'cashOnDelivery' => getWebConfig(name: 'cash_on_delivery'),
            'digitalPayment' => getWebConfig(name: 'digital_payment'),
            'offlinePayment' => getWebConfig(name: 'offline_payment'),
        ]);
    }

    public function updatePaymentOption(Request $request): RedirectResponse
    {
        $this->businessSettingRepo->updateOrInsert(type: 'cash_on_delivery', value: json_encode(['status' => $request->get('cash_on_delivery', 0)]));
        $this->businessSettingRepo->updateOrInsert(type: 'digital_payment', value: json_encode(['status' => $request->get('digital_payment', 0)]));
        $this->businessSettingRepo->updateOrInsert(type: 'offline_payment', value: json_encode(['status' => $request->get('offline_payment', 0)]));
        Toastr::success(translate('Successfully_Updated'));
        return back();
    }

    public function UpdatePaymentConfig(PaymentMethodUpdateRequest $request): RedirectResponse
    {
        collect(['status'])->each(fn($item, $key) => $request[$item] = $request->has($item) ? (int)$request[$item] : 0);
        $settings = $this->settingRepo->getFirstWhere(params: ['key_name'=>$request['gateway'], 'settings_type'=>'payment_config']);
        $additionalDataImage = $settings['additional_data'] != null ? json_decode($settings['additional_data']) : null;
        if ($request->hasFile('gateway_image')) {
            $gatewayImage = $this->file_uploader('payment_modules/gateway_image/', 'png', $request->file('gateway_image'), $additionalDataImage != null ? $additionalDataImage->gateway_image : '');
        } else {
            $gatewayImage = $additionalDataImage != null ? $additionalDataImage->gateway_image : '';
        }
        $request->validate(['gateway_title' => 'required']);

        $status = $request['status'] ?? 0;
        if ($request['status'] == 1) {
            $gateway = $this->settingRepo->getFirstWhere(params: ['key_name' => $request['gateway'], 'settings_type' => 'payment_config']);
            if ($gateway) {
                $paymentGatewayPublishedStatus = config('get_payment_publish_status') ?? 0;
                $paymentGatewaysList = $this->settingRepo->getListWhereIn(
                    whereInFilters: ['settings_type' => ['payment_config'], 'key_name' => GlobalConstant::DEFAULT_PAYMENT_GATEWAYS],
                    dataLimit: 'all',
                );
                $currencies = $this->currencyRepo->getListWhere(
                    dataLimit: 'all',
                );
                $checkedData = self::checkPaymentGatewaySupportedCurrencies($gateway, $currencies, $paymentGatewaysList);
                if ($checkedData['is_enabled_to_use'] != 1) {
                    $status = 0;
                    Toastr::error(translate(GATEWAYS_STATUS_UPDATE_FAIL['message']));
                } else {
                    Toastr::success(translate(GATEWAYS_DEFAULT_UPDATE_200['message']));
                }
            }
        }

        $validated = $request->validated();
        if ($request['gateway'] === 'click_pesa') {
            $validated = $this->prepareClickPesaConfigValues($request, $settings, $validated);
        }

        $this->settingRepo->updateOrInsert(params: ['key_name' => $request['gateway'], 'settings_type' => 'payment_config'], data: [
            'key_name' => $request['gateway'],
            'live_values' => $validated,
            'test_values' => $validated,
            'settings_type' => 'payment_config',
            'mode' => $request['mode'],
            'is_active' => $status,
            'additional_data' => json_encode(['gateway_title' => $request['gateway_title'],'gateway_image' => $gatewayImage]),
        ]);

        return back();
    }

    public function testClickPesaConnection(): JsonResponse
    {
        try {
            app(ClickPesaService::class)->generateToken();

            return response()->json([
                'status' => 1,
                'message' => translate('ClickPesa_connection_successful'),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 0,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function prepareClickPesaConfigValues(PaymentMethodUpdateRequest $request, $settings, array $validated): array
    {
        $previousLive = $settings['live_values'] ?? [];
        $previousTest = $settings['test_values'] ?? [];

        foreach (['client_id', 'api_key', 'api_secret', 'webhook_secret'] as $field) {
            $value = $request->input($field);
            if ($value === null || $value === '') {
                $validated[$field] = $previousLive[$field] ?? $previousTest[$field] ?? '';
            } else {
                $validated[$field] = ClickPesaService::encryptCredential($value);
            }
        }

        $validated['checksum_enabled'] = (int) $request->input('checksum_enabled', 0);
        $validated['enabled_methods'] = array_values($request->input('enabled_methods', ClickPesaService::defaultEnabledMethods()));
        $validated['bill_payment_mode'] = $request->input('bill_payment_mode', 'EXACT');

        return $validated;
    }
}
