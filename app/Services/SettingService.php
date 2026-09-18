<?php

namespace App\Services;

use App\Repositories\Interfaces\Setting\SettingRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'settings.all';

    public function __construct(
        protected SettingRepositoryInterface $settingRepository
    ) {}

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
        $this->settingRepository->set($key, $value);

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
            $this->settingRepository->set($key, $value);
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
            fn () => $this->settingRepository->all(),
        ));
    }

    /**
     * Validation rules for the settings form/schema in config/settings.php -
     * shared by the Livewire form and the resource controller's update
     * request so both validate the same dynamic, schema-driven fields the
     * same way.
     *
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->schema() as $group) {
            foreach ($group['fields'] as $key => $field) {
                $rules["values.{$key}"] = match ($field['type']) {
                    'boolean' => ['boolean'],
                    'select' => ['nullable', 'string', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                    default => ['nullable', 'string', 'max:2000'],
                };
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    public function schema(): array
    {
        /** @var array<string, array{label: string, fields: array<string, array<string, mixed>>}> $schema */
        $schema = config('settings', []);

        return $schema;
    }
}
