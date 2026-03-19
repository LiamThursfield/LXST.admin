<?php

use App\Models\User;

test('security settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('admin.settings.security.edit'));

    $response->assertOk();
});
