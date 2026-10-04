<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Payment\ClickPesaService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClickPesaServiceTest extends TestCase
{
    public function test_it_normalizes_tanzanian_phone_numbers(): void
    {
        $this->assertSame('255712345678', ClickPesaService::normalizeTanzanianPhone('0712 345 678'));
        $this->assertSame('255612345678', ClickPesaService::normalizeTanzanianPhone('+255 612 345 678'));
        $this->assertSame('255765432100', ClickPesaService::normalizeTanzanianPhone('765432100'));
    }

    public function test_it_rejects_invalid_tanzanian_phone_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClickPesaService::normalizeTanzanianPhone('0812 345 678');
    }

    public function test_it_creates_order_independent_checksums_without_checksum_fields(): void
    {
        $service = new ClickPesaService($this->clickPesaSetting());

        $payload = [
            'amount' => '1000.00',
            'currency' => 'TZS',
            'customer' => [
                'phone' => '255712345678',
                'name' => 'Jane Customer',
            ],
            'checksum' => 'ignored',
            'checksumMethod' => 'ignored',
        ];

        $samePayloadDifferentOrder = [
            'checksumMethod' => 'ignored',
            'customer' => [
                'name' => 'Jane Customer',
                'phone' => '255712345678',
            ],
            'currency' => 'TZS',
            'amount' => '1000.00',
            'checksum' => 'ignored',
        ];

        $this->assertSame(
            $service->createPayloadChecksum($payload),
            $service->createPayloadChecksum($samePayloadDifferentOrder),
        );
    }

    private function clickPesaSetting(): Setting
    {
        $setting = new Setting();
        $setting->key_name = ClickPesaService::PROVIDER;
        $setting->settings_type = 'payment_config';
        $setting->mode = 'live';
        $setting->live_values = [
            'client_id' => 'client-id',
            'api_key' => 'api-key',
            'api_secret' => 'checksum-secret',
        ];

        return $setting;
    }
}
