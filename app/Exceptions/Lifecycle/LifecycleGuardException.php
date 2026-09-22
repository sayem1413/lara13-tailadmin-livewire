<?php

namespace App\Exceptions\Lifecycle;

use RuntimeException;

/**
 * Common base for every guard exception this module throws
 * (CircularReferenceException, ChildrenExistException,
 * ParentNotActiveException, OrphanRemovalBlockedException,
 * RetainedRecordException) - see docs/lifecycle-integrity.md's "Guard
 * failures" section: all five are regular, foreseeable business-rule
 * failures meant to be caught at the controller/Livewire layer and
 * surfaced as an error toast, never a generic 500. Lets a caller that
 * touches a Lifecycle-governed model catch this one type instead of
 * listing all five (and staying correct as new guard exceptions are
 * added later).
 */
abstract class LifecycleGuardException extends RuntimeException {}
