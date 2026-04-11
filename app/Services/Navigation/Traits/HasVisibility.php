<?php

namespace App\Services\Navigation\Traits;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

trait HasVisibility
{
    protected mixed $gate = null;

    /**
     * Pass a Closure, a string generic Gate permission name, or a boolean
     * Should return true if 'visible'
     */
    public function gate(mixed $gate): static
    {
        $this->gate = $gate;

        return $this;
    }

    public function getGate(): mixed
    {
        return $this->gate;
    }

    /**
     * Determines if the Link is viewable
     * If provided, checks against the given user where necessary
     */
    public function isVisible(?Authenticatable $user): bool
    {
        if ($this->gate === null) {
            return true;
        }

        if (is_bool($this->gate)) {
            return $this->gate;
        }

        if ($this->gate instanceof Closure) {
            return (bool) call_user_func($this->gate, $user);
        }

        if (is_string($this->gate)) {
            return $user ? Gate::forUser($user)->allows($this->gate) : Gate::allows($this->gate);
        }

        return (bool) $this->gate;
    }
}
