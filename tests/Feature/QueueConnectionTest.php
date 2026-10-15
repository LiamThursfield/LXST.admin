<?php

test('the database queue always uses the central connection, where the jobs table lives', function () {
    // Tenancy swaps the default connection to the tenant's database, so a queue using the
    // default connection would try to write jobs into the tenant database and fail
    expect(config('queue.connections.database.connection'))
        ->not->toBeNull()
        ->toBe(config('tenancy.database.central_connection'));
});
