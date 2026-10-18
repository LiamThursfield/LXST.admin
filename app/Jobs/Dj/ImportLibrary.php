<?php

namespace App\Jobs\Dj;

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Models\Dj\LibraryImport;
use App\Services\Dj\Import\Exceptions\InvalidImportFile;
use App\Services\Dj\Import\ImportSource;
use App\Services\Dj\Import\LibraryImporter;
use App\Services\Dj\Import\RekordboxDatabase;
use App\Services\Dj\Import\RekordboxXml;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports an uploaded file into its library, recording progress and the outcome on the import.
 * The upload is deleted afterwards, whether the import worked or not.
 */
class ImportLibrary implements ShouldQueue
{
    use Queueable;

    /** A failed import is re-run by uploading again, not retried */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public LibraryImport $import) {}

    public function handle(LibraryImporter $importer): void
    {
        $import = $this->import;
        $import->update(['status' => ImportStatus::Processing, 'started_at' => CarbonImmutable::now(), 'error' => null]);

        $localPath = null;

        try {
            // The disk may not be local (e.g. S3), and SQLite needs a local file, so work on a copy
            $localPath = $this->copyToLocalFile();
            $source = $this->sourceFor($localPath);
            $import->update(['total' => $source->trackCount()]);

            $counts = $importer->import(
                $import->library,
                $source,
                (int) config('dj.imports.chunk_size'),
                function (int $processed) use ($import): void {
                    $import->update(['processed' => $processed]);
                },
            );

            $import->update([
                'status' => ImportStatus::Completed,
                'counts' => $counts,
                'finished_at' => CarbonImmutable::now(),
            ]);
        } finally {
            if ($localPath !== null) {
                @unlink($localPath);
            }

            $this->deleteUpload();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update([
            'status' => ImportStatus::Failed,
            // Problems with the file are worth showing; anything else is reported, not shown
            'error' => $exception instanceof InvalidImportFile ? $exception->getMessage() : __('dj.imports.errors.failed'),
            'finished_at' => CarbonImmutable::now(),
        ]);

        $this->deleteUpload();
    }

    protected function sourceFor(string $path): ImportSource
    {
        return match ($this->import->source) {
            LibrarySource::RekordboxDb => new RekordboxDatabase($path),
            LibrarySource::RekordboxXml => new RekordboxXml($path),
            default => throw new InvalidImportFile(__('dj.imports.errors.unsupported_source')),
        };
    }

    protected function copyToLocalFile(): string
    {
        $localPath = (string) tempnam(sys_get_temp_dir(), 'dj-import-');
        $stream = Storage::disk(config('dj.imports.disk'))->readStream((string) $this->import->file_path);

        if (! is_resource($stream)) {
            throw new InvalidImportFile(__('dj.imports.errors.missing_file'));
        }

        file_put_contents($localPath, $stream);
        fclose($stream);

        return $localPath;
    }

    protected function deleteUpload(): void
    {
        if ($this->import->file_path === null) {
            return;
        }

        Storage::disk(config('dj.imports.disk'))->delete($this->import->file_path);
        $this->import->update(['file_path' => null]);
    }
}
