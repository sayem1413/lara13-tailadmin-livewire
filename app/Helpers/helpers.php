<?php

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\ComponentAttributeBag;

if (! function_exists('setting')) {
    /**
     * Get or set an application setting.
     *
     * Call with no arguments to get the SettingService instance,
     * with one argument to read a value, or with two to write one.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingService::class);

        if (is_null($key)) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('wireModelName')) {
    /**
     * Extracts the bound property/path from a `wire:model` (or any
     * `wire:model.<modifier>...`, e.g. `.live.debounce.300ms`) attribute on
     * a Blade component's attribute bag — Blade stores the modifiers as
     * part of the literal attribute key, so an exact `wire:model` lookup
     * misses a modified one. Every new form input/textarea/toggle/date
     * component uses this to know which field to show a validation error
     * for, without requiring a separate explicit prop for it.
     */
    function wireModelName(ComponentAttributeBag $attributes): ?string
    {
        foreach ($attributes->getAttributes() as $key => $value) {
            if ($key === 'wire:model' || str_starts_with($key, 'wire:model.')) {
                return $value;
            }
        }

        return null;
    }
}

if (! function_exists('formatPrice')) {
    /**
     * Formats a raw number into a clean currency format. Defaults to Taka
     * (৳) since this app has no other currency anywhere — every Billing
     * view already passes '৳' explicitly; a couple of Diagnostic views
     * previously omitted it and silently showed a '$' instead.
     *
     * A negative amount (e.g. DashboardService::metrics()'s netProfit,
     * once a branch's approved expenses exceed its revenue for the
     * month — the first caller able to pass a negative value here) puts
     * the minus sign before the currency symbol ("-৳500.00"), not after
     * it ("৳-500.00", what a bare `$currency.number_format($amount, 2)`
     * produces) — number_format() itself only ever puts the sign at the
     * very start of its own output.
     */
    function formatPrice(float $amount, string $currency = '৳'): string
    {
        return ($amount < 0 ? '-' : '').$currency.number_format(abs($amount), 2);
    }
}

if (! function_exists('applySort')) {
    /**
     * Apply sorting to an Eloquent query.
     *
     * Example:
     * [
     *     'name' => ['asc', 'desc'],
     *     'code' => ['asc', 'desc'],
     * ]
     */
    function applySort(
        Builder $query,
        ?string $sort = 'newest',
        array $allowedColumns = []
    ): Builder {
        $sort = $sort ?: 'newest';

        /*
         * Special predefined sorts.
         */
        switch ($sort) {

            case 'newest':
                return $query->orderByDesc('id');

            case 'oldest':
                return $query->orderBy('id');

            default:
                break;
        }

        /*
         * Expected format:
         *
         * name_asc
         * name_desc
         * code_asc
         * code_desc
         */
        if (preg_match('/^(.+)_(asc|desc)$/', $sort, $matches)) {

            $column = $matches[1];
            $direction = $matches[2];

            /*
             * Security:
             * Never allow arbitrary column names from user input.
             */
            if (
                empty($allowedColumns)
                || in_array($column, $allowedColumns, true)
            ) {
                return $query->orderBy($column, $direction);
            }
        }

        /*
         * Fallback.
         */
        return $query->orderByDesc('id');
    }
}

if (! function_exists('applyFilters')) {
    /**
     * Apply multiple filters.
     *
     * Example:
     *
     * [
     *     'is_active' => true,
     *     'branch_id' => 5,
     *     'city' => 'Dhaka',
     * ]
     */
    function applyFilters(
        Builder $query,
        array $filters = [],
        array $allowedFilters = []
    ): Builder {
        foreach ($filters as $column => $value) {

            /*
             * Ignore empty filters.
             */
            if ($value === null || $value === '') {
                continue;
            }

            /*
             * Security:
             * Only allow explicitly permitted columns.
             */
            if (
                ! empty($allowedFilters)
                && ! in_array($column, $allowedFilters, true)
            ) {
                continue;
            }

            /*
             * Handle arrays as WHERE IN.
             */
            if (is_array($value)) {
                $query->whereIn($column, $value);

                continue;
            }

            /*
             * Normal equality filter.
             */
            $query->where($column, $value);
        }

        return $query;
    }
}

if (! function_exists('applyTrashedFilter')) {
    /**
     * Apply a soft-delete visibility filter to an Eloquent query.
     *
     * 'only' -> only soft-deleted rows (onlyTrashed)
     * 'with' -> both trashed and non-trashed rows (withTrashed)
     * anything else (null, '', 'without') -> default Eloquent behavior, excludes trashed rows
     */
    function applyTrashedFilter(Builder $query, ?string $trashed = null): Builder
    {
        return match ($trashed) {
            'only' => $query->onlyTrashed(),
            'with' => $query->withTrashed(),
            default => $query,
        };
    }
}

if (! function_exists('applySearch')) {
    /**
     * Apply text search across multiple columns.
     */
    function applySearch(
        Builder $query,
        ?string $search,
        array $columns = []
    ): Builder {
        if (
            empty($search)
            || empty($columns)
        ) {
            return $query;
        }

        $query->where(function (Builder $query) use ($search, $columns) {

            foreach ($columns as $index => $column) {

                if ($index === 0) {
                    $query->where(
                        $column,
                        'like',
                        "%{$search}%"
                    );
                } else {
                    $query->orWhere(
                        $column,
                        'like',
                        "%{$search}%"
                    );
                }
            }
        });

        return $query;
    }
}
