<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Clear settings cache on model changes
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('app_settings'));
        static::deleted(fn () => Cache::forget('app_settings'));
    }
}
