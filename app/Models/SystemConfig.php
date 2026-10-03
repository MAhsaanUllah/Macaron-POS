<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SystemConfig extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'strict_ingredient_tracking',
        'fbr_pos_id',
        'fbr_bearer_token',
        'fbr_environment',
        'kitchen_printer_ip',
        'cashier_printer_ip',
        'is_setup_completed',
        'tax_rate',
        'discount_limit_pct',
        'lan_access_enabled',
        'fbr_enabled',
        'seller_province',
        'fbr_scenario_id',
        'kitchen_printer_name',
        'cashier_printer_name',
        'business_type',
        'pickup_enabled',
        'delivery_enabled',
        'card_enabled',
        'card_terminal_name',
        'raast_enabled',
        'raast_qr_path',
    ];

    protected $casts = [
        'strict_ingredient_tracking' => 'boolean',
        'is_setup_completed' => 'boolean',
        'tax_rate' => 'decimal:2',
        'discount_limit_pct' => 'decimal:2',
        'fbr_enabled' => 'boolean',
        'pickup_enabled' => 'boolean',
        'delivery_enabled' => 'boolean',
        'card_enabled' => 'boolean',
        'raast_enabled' => 'boolean',
        'lan_access_enabled' => 'boolean',
    ];

    protected function fbrBearerToken(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (! $value || ! str_starts_with($value, 'eyJpdiI6')) {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    return null;
                }
            },
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}
