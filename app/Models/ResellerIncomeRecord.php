<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerIncomeRecord extends Model
{
    protected $table = 'reseller_income_records';

    protected $fillable = [
        'reseller_id',
        'vpn_user_id',
        'a_order_id',
        'biz_order_no',
        'kind',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    public function vpnUser(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    /** 本笔流水对应的 A 站订阅订单 */
    public function subscriptionOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'a_order_id');
    }
}
