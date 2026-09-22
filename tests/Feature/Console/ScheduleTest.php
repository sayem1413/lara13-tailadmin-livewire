<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules activitylog:clean to run daily', function () {
    // withSchedule()'s callback only registers once the console kernel
    // actually boots (see Illuminate\Foundation\Configuration\
    // ApplicationBuilder::withSchedule(), wrapped in Artisan::starting()) -
    // a plain HTTP-context test never triggers that, so running any
    // Artisan command first is what makes Schedule::events() populated.
    // Artisan::starting() can itself fire more than once within a single
    // test process (e.g. once for RefreshDatabase's internal migrate call),
    // re-registering the same schedule entry each time - harmless in a real
    // per-process console invocation, but it means this asserts every
    // matching entry has the right frequency rather than an exact count.
    $this->artisan('inspire');

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'activitylog:clean'));

    expect($events)->not->toBeEmpty()
        ->and($events->pluck('expression')->unique()->all())->toBe(['0 0 * * *']);
});
