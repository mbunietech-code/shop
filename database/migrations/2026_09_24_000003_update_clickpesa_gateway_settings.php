<?php

use App\Services\Payment\ClickPesaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'gateway' => 'click_pesa',
            'mode' => 'live',
            'status' => 0,
            'client_id' => '',
            'api_key' => '',
            'api_secret' => '',
            'webhook_secret' => '',
            'checksum_enabled' => 0,
            'bill_payment_mode' => 'EXACT',
            'enabled_methods' => ClickPesaService::defaultEnabledMethods(),
        ];

        $setting = DB::table('addon_settings')
            ->where('key_name', 'click_pesa')
            ->where('settings_type', 'payment_config')
            ->first();

        if (!$setting) {
            DB::table('addon_settings')->insert([
                'id' => (string) Str::uuid(),
                'key_name' => 'click_pesa',
                'live_values' => json_encode($defaults),
                'test_values' => json_encode($defaults),
                'settings_type' => 'payment_config',
                'mode' => 'live',
                'is_active' => 0,
                'additional_data' => json_encode([
                    'gateway_title' => 'ClickPesa',
                    'gateway_image' => '',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $liveValues = array_replace($defaults, json_decode($setting->live_values ?: '{}', true) ?: []);
        $testValues = array_replace($defaults, json_decode($setting->test_values ?: '{}', true) ?: []);
        $liveValues['enabled_methods'] = $liveValues['enabled_methods'] ?: ClickPesaService::defaultEnabledMethods();
        $testValues['enabled_methods'] = $testValues['enabled_methods'] ?: ClickPesaService::defaultEnabledMethods();

        DB::table('addon_settings')
            ->where('id', $setting->id)
            ->update([
                'live_values' => json_encode($liveValues),
                'test_values' => json_encode($testValues),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
