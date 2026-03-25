<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResellerApiKey extends Model
{
    protected $table = 'reseller_api_keys';

    public $timestamps = false;

    protected $fillable = ['reseller_id', 'api_key', 'name'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $key) {
            if (empty($key->api_key)) {
                $key->api_key = 'rk_' . Str::random(32);
            }
        });
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }
}
