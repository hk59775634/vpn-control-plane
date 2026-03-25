<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerPaymentOrder extends Model
{
    protected $fillable = [
        'reseller_id',
        'out_trade_no',
        'amount_cents',
        'status',
        'trade_no',
        'pay_type',
        'meta',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'meta' => 'array',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }
}
