<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'client_token',
        'status',
        'total_cents',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
