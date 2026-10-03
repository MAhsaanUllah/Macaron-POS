<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'admin_name' => 'Malik Ahmed',
            'admin_email' => 'owner@example.com',
            'admin_password' => 'macaron123',
            'shop_name' => 'Mithai Mahal, Gujranwala',
            'phone_number' => '0300-1234567',
            'persona' => 'sweets',
        ];
    }

    public function test_setup_completes_and_seeds_demo_catalog(): void
    {
        $this->post('/setup', $this->payload())
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => 'admin',
        ]);
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseCount('items', 8);
    }

    public function test_setup_forces_seeder_so_production_desktop_never_prompts(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', Mockery::on(
                fn ($args) => ($args['--class'] ?? null) === 'SweetShopSeeder'
                    && ($args['--force'] ?? false) === true
            ));

        $this->post('/setup', $this->payload())
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com']);
    }

    public function test_replayed_setup_post_is_rejected_after_completion(): void
    {
        $this->post('/setup', $this->payload())->assertRedirect(route('dashboard'));

        $this->post('/setup', [
            'admin_name' => 'QA Attacker',
            'admin_email' => 'attacker@example.com',
            'admin_password' => 'attacker123',
            'shop_name' => 'Overwritten Shop',
            'phone_number' => '0300-9999999',
            'persona' => 'sweets',
        ])->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
        $this->assertDatabaseHas('system_settings', ['shop_name' => 'Mithai Mahal, Gujranwala']);
    }

    public function test_setup_rejects_phone_numbers_that_fail_the_format_check(): void
    {
        $this->post('/setup', array_merge($this->payload(), ['phone_number' => '0300']))
            ->assertSessionHasErrors('phone_number');

        $this->assertDatabaseCount('users', 0);
    }
}
