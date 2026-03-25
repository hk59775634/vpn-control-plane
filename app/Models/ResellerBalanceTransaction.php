<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerBalanceTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'reseller_balance_transactions';

    protected $fillable = [
        'reseller_id',
        'amount_cents',
        'balance_after_cents',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }
}
