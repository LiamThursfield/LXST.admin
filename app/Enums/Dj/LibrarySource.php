<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * Where a DJ library's tracks come from
 */
enum LibrarySource: string
{
    use HasLabels;

    /** A Rekordbox XML export, uploaded in the admin */
    case RekordboxXml = 'rekordbox_xml';

    /** A decrypted Rekordbox `master.db`, uploaded in the admin */
    case RekordboxDb = 'rekordbox_db';

    /** Pushed by the Rekordbox CLI tool through the API */
    case CliSync = 'cli_sync';

    /**
     * The sources a file can be uploaded from in the admin, the most detailed first
     *
     * @return list<self>
     */
    public static function uploadable(): array
    {
        return [self::RekordboxDb, self::RekordboxXml];
    }

    /**
     * The file extension an upload from this source has
     */
    public function fileExtension(): ?string
    {
        return match ($this) {
            self::RekordboxDb => 'db',
            self::RekordboxXml => 'xml',
            self::CliSync => null,
        };
    }
}
