<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceiptProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    private function dashboardHtml(array $settings, float $taxRate = 0): string
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => $taxRate]);
        SystemSetting::create($settings);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);
        Shift::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'opening_balance' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);

        return $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
    }

    public function test_receipt_prints_profile_fields_only_and_never_demo_fallbacks(): void
    {
        $html = $this->dashboardHtml([
            'shop_name' => 'Mithai Mahal',
            'phone_number' => '0300-1234567',
            'address' => '',
            'currency_symbol' => 'Rs.',
            'tax_number' => '',
        ]);

        $this->assertStringContainsString('Mithai Mahal', $html);
        $this->assertStringContainsString('Ph: 0300-1234567', $html);
        $this->assertStringNotContainsString('Wapda Town', $html);
        $this->assertStringNotContainsString('+92 300 1234567', $html);
        $this->assertStringNotContainsString('NTN/STRN', $html);
        $this->assertStringNotContainsString('#01-MAIN', $html);
        $this->assertStringNotContainsString('SALES TAX (16%)', $html);
    }

    public function test_receipt_renders_address_tax_logo_currency_and_tax_rate_when_configured(): void
    {
        $html = $this->dashboardHtml([
            'shop_name' => 'Mithai Mahal',
            'phone_number' => null,
            'address' => 'Main Bazaar, Gujranwala',
            'currency_symbol' => 'PKR',
            'tax_number' => '1234567-8',
            'logo_path' => 'logos/shop.png',
        ], 16);

        $this->assertStringContainsString('Main Bazaar, Gujranwala', $html);
        $this->assertStringContainsString('NTN/STRN: 1234567-8', $html);
        $this->assertStringContainsString('storage/logos/shop.png', $html);
        $this->assertStringContainsString('SALES TAX (16%)', $html);
        $this->assertStringContainsString('PKR 0.00', $html);
        $this->assertStringNotContainsString('Ph:', $html);
    }
}
