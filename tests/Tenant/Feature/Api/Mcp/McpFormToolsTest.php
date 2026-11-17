<?php

use App\Enums\Api\ApiTokenAbility;
use App\Models\Crm\Form;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = mcpUser($this);
    $this->token = mcpToken($this->user, [ApiTokenAbility::McpCrmForms]);
});

test('a contact form is created with its fields and read back by slug', function () {
    $result = mcpCall($this, $this->token, 'create_form', [
        'name' => 'Contact',
        'slug' => 'contact',
        'email_recipients' => ['hello@example.com'],
        'fields' => [
            ['type' => 'crm_first_name', 'name' => 'Name', 'is_required' => true],
            ['type' => 'crm_email', 'name' => 'Email', 'is_required' => true],
            ['type' => 'textarea', 'name' => 'Message', 'settings' => ['rows' => 6]],
        ],
    ]);

    expect($result['isError'])->toBeFalse()
        ->and(Form::sole()->fields->pluck('slug')->all())->toBe(['name', 'email', 'message']);

    $form = mcpCall($this, $this->token, 'get_form', ['slug' => 'contact'])['structuredContent']['form'];

    expect($form['email_recipients'])->toBe(['hello@example.com'])
        ->and($form['fields'][2]['settings'])->toBe(['rows' => 6]);
});

test('a form\'s fields can be reordered and added to, but not removed', function () {
    mcpCall($this, $this->token, 'create_form', ['name' => 'Contact', 'slug' => 'contact', 'fields' => [['type' => 'crm_email', 'name' => 'Email'], ['type' => 'textarea', 'name' => 'Message']]]);
    $form = Form::sole();

    $updated = mcpCall($this, $this->token, 'update_form', [
        'id' => $form->id,
        'success_message' => 'Thanks!',
        'fields' => [['type' => 'textarea', 'name' => 'Message', 'slug' => 'message'], ['type' => 'crm_email', 'name' => 'Email', 'slug' => 'email'], ['type' => 'text', 'name' => 'Company']],
    ]);

    expect($updated['isError'])->toBeFalse()
        ->and($form->fresh()->success_message)->toBe('Thanks!')
        ->and($form->fresh()->fields->pluck('slug')->all())->toBe(['message', 'email', 'company']);

    $removed = mcpCall($this, $this->token, 'update_form', ['id' => $form->id, 'fields' => [['type' => 'text', 'name' => 'Company']]]);

    expect($removed['isError'])->toBeTrue()
        ->and($form->fresh()->fields)->toHaveCount(3);
});
