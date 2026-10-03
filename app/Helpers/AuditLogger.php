<?php

namespace App\Helpers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(string $action, string $description, ?string $orderId = null): AuditLog
    {
        $userId = static::resolveUserId();

        return AuditLog::create([
            'user_id' => $userId,
            'action_performed' => $action,
            'description' => $description,
            'order_id' => $orderId,
            'ip_address' => Request::ip(),
        ]);
    }

    public static function logIfDiscountThreshold(string $role, float $discount, float $subtotal, string $orderId): ?AuditLog
    {
        $threshold = 0.15;
        if ($role === User::ROLE_MANAGER || $role === User::ROLE_CASHIER) {
            $discountPct = $subtotal > 0 ? $discount / $subtotal : 0;
            if ($discountPct > $threshold) {
                $username = static::resolveUserName();
                $currency = optional(SystemSetting::first())->currency_symbol ?? 'Rs.';

                return static::log(
                    'discount_exceeded_threshold',
                    "{$username} authorized a {$currency}".number_format($discount, 2).' manual discount ('.round($discountPct * 100)."%) on Order #{$orderId}",
                    $orderId
                );
            }
        }

        return null;
    }

    public static function logStatusChange(string $oldStatus, string $newStatus, string $orderId): AuditLog
    {
        $username = static::resolveUserName();

        return static::log(
            'order_status_changed',
            "{$username} changed Order #{$orderId} status from '{$oldStatus}' to '{$newStatus}'",
            $orderId
        );
    }

    public static function logItemRemoved(string $itemName, int $quantity, string $orderId): AuditLog
    {
        $username = static::resolveUserName();

        return static::log(
            'cart_item_removed',
            "{$username} removed {$quantity}x {$itemName} from Order #{$orderId}",
            $orderId
        );
    }

    private static function resolveUserId(): string
    {
        return (string) auth()->id();
    }

    private static function resolveUserName(): string
    {
        return auth()->user()?->name ?? 'System';
    }
}
