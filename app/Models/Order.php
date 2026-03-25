<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'vpn_user_id',
        'product_id',
        'reseller_id',
        'biz_order_no',
        'status',
        'activated_at',
        'last_renewed_at',
        'expires_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_renewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vpnUser(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    /** B 站同步的收入流水（新购/续费各一条完整业务单号） */
    public function incomeRecords(): HasMany
    {
        return $this->hasMany(ResellerIncomeRecord::class, 'a_order_id', 'id');
    }
}
