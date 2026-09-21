<?php

namespace App\Contracts;

/**
 * Implemented by any model using the App\Models\Concerns\HasActiveStatus
 * trait, which satisfies this contract on its own. Declared separately
 * from LifecycleAware so a model can adopt custom-status events alone,
 * without also opting into relationship cascades.
 */
interface LifecycleStatusAware
{
    public function activate(): void;

    public function deactivate(): void;

    public function isLifecycleActive(): bool;
}
