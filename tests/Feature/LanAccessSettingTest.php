<?php

namespace Tests\Feature;

use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LanAccessSettingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'lan-owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);

        return $owner;
    }

    public function test_lan_access_defaults_to_false(): void
    {
        $config = SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $this->assertFalse($config->fresh()->lan_access_enabled);
    }

    public function test_owner_can_enable_lan_access(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $this->actingAs($this->owner())->post('/settings/update', [
            'shop_name' => 'LAN Sweets',
            'phone_number' => '0300-0000000',
            'lan_access_enabled' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(SystemConfig::first()->lan_access_enabled);
    }

    public function test_saving_lan_setting_writes_desktop_json_when_data_dir_present(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $dir = storage_path('tmp-lan-desktop');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        putenv('MACARON_DATA_DIR='.$dir);
        try {
            $this->actingAs($this->owner())->post('/settings/update', [
                'shop_name' => 'LAN Sweets',
                'phone_number' => '0300-0000000',
                'lan_access_enabled' => '1',
            ])->assertSessionHasNoErrors();
            $payload = json_decode(file_get_contents($dir.'/desktop.json'), true);
            $this->assertTrue($payload['lan_enabled']);
        } finally {
            putenv('MACARON_DATA_DIR');
            @unlink($dir.'/desktop.json');
            @rmdir($dir);
        }
    }

    public function test_no_desktop_json_written_when_data_dir_absent(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        putenv('MACARON_DATA_DIR');
        $this->actingAs($this->owner())->post('/settings/update', [
            'shop_name' => 'LAN Sweets',
            'phone_number' => '0300-0000000',
            'lan_access_enabled' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFileDoesNotExist(storage_path('tmp-lan-desktop/desktop.json'));
    }

    public function test_settings_page_shows_lan_toggle(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $this->actingAs($this->owner())->get('/settings')
            ->assertOk()
            ->assertSee('lan_access_enabled')
            ->assertSee('Allow tablet/phone access on this network');
    }

    public function test_saving_lan_disable_writes_desktop_json_false_when_data_dir_present(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $dir = storage_path('tmp-lan-desktop');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        putenv('MACARON_DATA_DIR='.$dir);
        try {
            $owner = $this->owner();
            $this->actingAs($owner)->post('/settings/update', [
                'shop_name' => 'LAN Sweets',
                'phone_number' => '0300-0000000',
                'lan_access_enabled' => '1',
            ])->assertSessionHasNoErrors();
            $this->actingAs($owner)->post('/settings/update', [
                'shop_name' => 'LAN Sweets',
                'phone_number' => '0300-0000000',
                'lan_access_enabled' => '0',
            ])->assertSessionHasNoErrors();
            $this->assertFileExists($dir.'/desktop.json');
            $payload = json_decode(file_get_contents($dir.'/desktop.json'), true);
            $this->assertFalse($payload['lan_enabled']);
        } finally {
            putenv('MACARON_DATA_DIR');
            @unlink($dir.'/desktop.json');
            @rmdir($dir);
        }
    }
}
