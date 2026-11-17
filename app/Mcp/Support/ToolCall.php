<?php

namespace App\Mcp\Support;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\McpOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * What a tool call did, collected while it runs, for the audit log
 */
class ToolCall
{
    public McpOutcome $outcome = McpOutcome::Error;

    public ?string $message = null;

    public ?string $targetType = null;

    public ?int $targetId = null;

    /** @var array<string, array{before: mixed, after: mixed}>|null */
    public ?array $changes = null;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $tool,
        public ApiTokenAbility $ability,
        public array $arguments,
    ) {}

    /**
     * What the tool acted on, e.g. ('page', 12)
     */
    public function target(string $type, Model|int|null $target): void
    {
        $this->targetType = $type;
        $this->targetId = $target instanceof Model ? (int) $target->getKey() : $target;
    }

    /**
     * Records the attributes that differ between two snapshots, two levels deep (so a page's
     * content and metadata are compared field by field). Pass null as `before` for something new.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public function changed(?array $before, array $after): void
    {
        $before = $this->flatten($before ?? []);
        $after = $this->flatten($after);
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = ['before' => Summary::of($old), 'after' => Summary::of($new)];
            }
        }

        $this->changes = $changes === [] ? null : $changes;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function flatten(array $values): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            if (is_array($value) && Arr::isAssoc($value)) {
                foreach ($value as $nestedKey => $nested) {
                    $flat["{$key}.{$nestedKey}"] = $nested;
                }

                continue;
            }

            $flat[(string) $key] = $value;
        }

        return $flat;
    }
}
