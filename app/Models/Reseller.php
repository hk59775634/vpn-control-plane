<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Reseller extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'balance_cents',
        'balance_enforced',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'balance_cents' => 'integer',
            'balance_enforced' => 'boolean',
        ];
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ResellerApiKey::class, 'reseller_id');
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(ResellerBalanceTransaction::class, 'reseller_id');
    }
}
