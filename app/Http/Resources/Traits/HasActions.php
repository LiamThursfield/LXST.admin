<?php

namespace App\Http\Resources\Traits;

use App\Support\UI\Action;
use Illuminate\Http\Request;

trait HasActions
{
    /**
     * Get the actions that can be performed for the current resource
     *
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        return [];
    }

    /**
     * Append the actions to the given resource, should be used in toArray(),
     * whereby $data is the array that is returned from toArray()
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function appendActions(Request $request, array $data): array
    {
        $actions = $this->getActions($request);

        if (! empty($actions)) {
            $data['actions'] = $actions;
        }

        return $data;
    }
}
