<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_number',
        'checkout_token',
        'order_type',
        'status',
        'subtotal',
        'tax',
        'discount',
        'grand_total',
        'payment_method',
        'amount_tendered',
        'change_amount',
        'transaction_reference',
        'customer_name',
        'customer_phone',
        'customer_id',
        'points_earned',
        'points_redeemed',
        'shift_id',
        'fbr_status',
        'fbr_invoice_number',
        'fbr_error',
        'fbr_submitted_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'points_earned' => 'decimal:2',
        'points_redeemed' => 'decimal:2',
        'fbr_submitted_at' => 'datetime',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
