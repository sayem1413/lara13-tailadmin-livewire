<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'settings.all';

    /**
     * Get a setting value by key, falling back to $default when it isn't set.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()->get($key, $default);
    }

    /**
     * Persist a setting value.
     */
    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Persist several settings at once.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * All settings, keyed by their setting key.
     *
     * Cached as a plain array rather than a Collection: Laravel's cache
     * stores reject unserializing arbitrary objects by default (see
     * config/cache.php's "serializable_classes"), so only plain arrays
     * and scalars round-trip through the cache reliably.
     *
     * @return Collection<string, mixed>
     */
    public function all(): Collection
    {
        return collect(Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        ));
    }
}
