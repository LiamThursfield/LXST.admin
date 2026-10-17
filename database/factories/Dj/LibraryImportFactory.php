<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryImport>
 */
class LibraryImportFactory extends Factory
{
    protected $model = LibraryImport::class;

    /**
     * Define the model's default state (a master.db import waiting to run).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory()->source(LibrarySource::RekordboxDb),
            'user_id' => null,
            'source' => LibrarySource::RekordboxDb,
            'status' => ImportStatus::Pending,
            'original_filename' => 'master.db',
            'file_path' => null,
            'total' => 0,
            'processed' => 0,
            'counts' => null,
            'error' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ImportStatus::Completed,
            'total' => 10,
            'processed' => 10,
            'counts' => ['tracks_added' => 10],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
