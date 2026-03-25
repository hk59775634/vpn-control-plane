<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'key';

    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'updated_at',
    ];

    public static function getValue(string $key): ?string
    {
        $v = static::query()->where('key', $key)->value('value');

        return $v === null ? null : (string) $v;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    public static function deleteKey(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }
}
