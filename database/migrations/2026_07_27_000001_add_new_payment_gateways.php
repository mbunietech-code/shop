<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $gateways = [
            [
                'key_name' => 'click_pesa',
                'live_values' => json_encode([
                    'gateway' => 'click_pesa',
                    'mode' => 'test',
                    'status' => 0,
                    'client_id' => '',
                    'api_key' => '',
                    'gateway_title' => 'Mobile Money',
                    'gateway_image' => '',
                ]),
                'test_values' => json_encode([
                    'gateway' => 'click_pesa',
                    'mode' => 'test',
                    'status' => 0,
                    'client_id' => '',
                    'api_key' => '',
                    'gateway_title' => 'Mobile Money',
                    'gateway_image' => '',
                ]),
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'is_active' => 0,
                'additional_data' => json_encode([
                    'gateway_title' => 'Mobile Money',
                    'gateway_image' => '',
                ]),
            ],
            [
                'key_name' => 'azam_pay',
                'live_values' => json_encode([
                    'gateway' => 'azam_pay',
                    'mode' => 'test',
                    'status' => 0,
                    'app_name' => '',
                    'client_id' => '',
                    'client_secret' => '',
                    'api_key' => '',
                    'gateway_title' => 'Azam Pay',
                    'gateway_image' => '',
                ]),
                'test_values' => json_encode([
                    'gateway' => 'azam_pay',
                    'mode' => 'test',
                    'status' => 0,
                    'app_name' => '',
                    'client_id' => '',
                    'client_secret' => '',
                    'api_key' => '',
                    'gateway_title' => 'Azam Pay',
                    'gateway_image' => '',
                ]),
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'is_active' => 0,
                'additional_data' => json_encode([
                    'gateway_title' => 'Azam Pay',
                    'gateway_image' => '',
                ]),
            ],
        ];

        $placeholderImage = 'placeholder-4-1.png';

        foreach ($gateways as $gateway) {
            $existing = DB::table('addon_settings')
                ->where('key_name', $gateway['key_name'])
                ->where('settings_type', 'payment_config')
                ->first();

            if (!$existing) {
                DB::table('addon_settings')->insert([
                    'id' => (string) Str::uuid(),
                    'key_name' => $gateway['key_name'],
                    'live_values' => $gateway['live_values'],
                    'test_values' => $gateway['test_values'],
                    'settings_type' => $gateway['settings_type'],
                    'mode' => $gateway['mode'],
                    'is_active' => $gateway['is_active'],
                    'additional_data' => $gateway['additional_data'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('addon_settings')
            ->whereIn('key_name', ['click_pesa', 'azam_pay'])
            ->where('settings_type', 'payment_config')
            ->delete();
    }
};
