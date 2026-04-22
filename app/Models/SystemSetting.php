<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['key', 'value', 'type'];

    /**
     * Retrieve a setting value by key, decrypting if necessary.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->type === 'encrypted'
            ? Crypt::decryptString($setting->value)
            : $setting->value;
    }

    /**
     * Store or update a setting value, encrypting if necessary.
     */
    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = $type === 'encrypted'
            ? Crypt::encryptString((string) $value)
            : $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type]
        );
    }

    /**
     * Retrieve all POS connection settings as an associative array.
     * Returns null values for keys that are not yet configured.
     */
    public static function getPosConnection(): array
    {
        $rows = static::whereIn('key', [
            'pos.host', 'pos.port', 'pos.database', 'pos.username', 'pos.password',
        ])->get()->keyBy('key');

        $decrypt = fn (string $k) => isset($rows[$k])
            ? Crypt::decryptString($rows[$k]->value)
            : null;

        $plain = fn (string $k) => $rows[$k]->value ?? null;

        return [
            'host'     => $plain('pos.host'),
            'port'     => $plain('pos.port') ?? '3306',
            'database' => $plain('pos.database'),
            'username' => $plain('pos.username'),
            'password' => $decrypt('pos.password'),
        ];
    }

    /**
     * True when all required POS connection fields are present in DB.
     */
    public static function hasPosConnection(): bool
    {
        return static::whereIn('key', ['pos.host', 'pos.database', 'pos.username'])->count() === 3;
    }
}
