<?php

namespace App\Support\UI;

use JsonSerializable;

/**
 * An action represents a single action that can be performed on a resource.
 */
class Action implements JsonSerializable
{
    public function __construct(
        protected string $label,
        protected string $url,
        protected string $method = 'GET',
        protected ?string $icon = null,
        protected bool $requireConfirmation = false,
        protected ?string $confirmationMessage = null,
    ) {}

    /**
     * Create an action instance with the given label and action url
     */
    public static function make(string $label, string $url): self
    {
        return new self($label, $url);
    }

    /**
     * The HTTP method that should be used for the request
     *
     * @return $this
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Set the icon that should be shown for the action
     *
     * @return $this
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Sets require confirmation to true, with an optional message
     *
     * @return $this
     */
    public function requireConfirmation(string $message = 'Are you sure you want to perform this action?'): self
    {
        $this->requireConfirmation = true;
        $this->confirmationMessage = $message;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            // @inertiajs/core Method is lowercase, so ensure we follow that convention
            // when serializing
            'method' => strtolower($this->method),
            'icon' => $this->icon,
            'requireConfirmation' => $this->requireConfirmation,
            'confirmationMessage' => $this->confirmationMessage,
        ];
    }
}
