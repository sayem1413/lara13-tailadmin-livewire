<?php

namespace App\Models\Concerns;

use App\Enums\LifecycleStatus;
use App\Events\Lifecycle\Activated;
use App\Events\Lifecycle\Activating;
use App\Events\Lifecycle\Deactivated;
use App\Events\Lifecycle\Deactivating;
use Illuminate\Support\Carbon;

/**
 * Gives a model activate()/deactivate() methods backed by an explicit
 * `lifecycle_status` state (rather than a boolean), firing custom events
 * Eloquent has no native equivalent for. Usable standalone; combine with
 * HasLifecycleIntegrity to also cascade the change to related records.
 *
 * Requires the model to have `lifecycle_status` (cast to
 * App\Enums\LifecycleStatus), `activated_at`, and `deactivated_at`
 * columns - see docs/lifecycle-integrity.md.
 *
 * @property LifecycleStatus $lifecycle_status
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 */
trait HasActiveStatus
{
    /**
     * Idempotent - activating an already-active record is a safe no-op,
     * not a re-fire of the activation events.
     */
    public function activate(): void
    {
        if ($this->lifecycle_status === LifecycleStatus::Active) {
            return;
        }

        event(new Activating($this));

        $this->forceFill([
            'lifecycle_status' => LifecycleStatus::Active,
            'activated_at' => now(),
        ])->save();

        event(new Activated($this));
    }

    /**
     * Idempotent - deactivating an already-inactive record is a safe
     * no-op, not a re-fire of the deactivation events.
     */
    public function deactivate(): void
    {
        if ($this->lifecycle_status === LifecycleStatus::Inactive) {
            return;
        }

        event(new Deactivating($this));

        $this->forceFill([
            'lifecycle_status' => LifecycleStatus::Inactive,
            'deactivated_at' => now(),
        ])->save();

        event(new Deactivated($this));
    }

    public function isLifecycleActive(): bool
    {
        return $this->lifecycle_status === LifecycleStatus::Active;
    }
}
