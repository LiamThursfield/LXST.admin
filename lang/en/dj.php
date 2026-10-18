<?php

/*
|--------------------------------------------------------------------------
| DJ Language Lines
|--------------------------------------------------------------------------
|
| Strings for the DJ module (library, gigs, song requests and stats).
| Enum labels live in enums.php.
|
*/

return [
    'libraries' => [
        'title' => 'Library',
        'description' => 'Your music libraries, imported from Rekordbox. Re-import any time: tracks are updated rather than duplicated.',
        'empty' => 'No libraries yet. Import your Rekordbox library to get started.',
        'tracks' => ':count tracks',
        'missing' => ':count missing',
        'missing_help' => 'In the library before, but not in the latest import. They\'re kept, as play history points at them.',
        'artists' => ':count artists',
        'sessions' => ':count history sessions',
        'never_imported' => 'Not imported yet',
        'last_imported' => 'Last imported :date',
        'import' => 'Import',
        'confirm_delete' => 'Delete the library ":name"? Its tracks, tags and play history will be deleted too. This can\'t be undone.',
        'flash' => [
            'deleted' => 'Library deleted.',
        ],
    ],

    'imports' => [
        'title' => 'Import history',
        'empty' => 'Nothing imported yet.',
        'create' => 'Import library',
        'start' => 'Start import',
        'by' => 'by :name',
        'columns' => [
            'library' => 'Library',
            'file' => 'File',
            'status' => 'Status',
            'results' => 'Results',
            'started' => 'Started',
        ],
        'fields' => [
            'source' => 'Import from',
            'library' => 'Library',
            'library_help' => 'Import into an existing library to update it, or create a new one.',
            'new_library' => 'New library',
            'library_name' => 'Library name',
            'file' => 'File',
            'file_help' => [
                'rekordbox_db' => 'A decrypted Rekordbox 6 or 7 master.db, up to :size MB.',
                'rekordbox_xml' => 'A Rekordbox XML export, up to :size MB.',
            ],
        ],
        'xml_help' => 'In Rekordbox, choose File, then Export Collection in xml format. The export has no play history or My Tags, so use a master.db for those.',
        'decrypt_help' => 'Rekordbox encrypts its master.db, so decrypt a copy first, e.g. with pyrekordbox. Never upload the copy Rekordbox is using.',
        'decrypt_link' => 'How to decrypt with pyrekordbox',
        'counts' => [
            'tracks_added' => ':count added',
            'tracks_updated' => ':count updated',
            'tracks_unchanged' => ':count unchanged',
            'tracks_missing' => ':count missing',
            'tracks_skipped' => ':count skipped',
            'history_sessions' => ':count history sessions',
        ],
        'flash' => [
            'started' => 'Import started. It carries on in the background.',
        ],
        'errors' => [
            'not_db' => 'Choose a master.db file.',
            'not_xml' => 'Choose an .xml file.',
            'not_rekordbox_xml' => 'This isn\'t a Rekordbox XML export. In Rekordbox, choose File, then Export Collection in xml format.',
            'malformed_xml' => 'This XML file is damaged or incomplete. Export it from Rekordbox again.',
            'too_large' => 'The file must be :size MB or smaller.',
            'not_decrypted' => 'This file isn\'t a decrypted database. Rekordbox encrypts master.db, so decrypt a copy first.',
            'not_rekordbox' => 'This isn\'t a Rekordbox database.',
            'unsupported_version' => 'Only Rekordbox 6 and 7 databases can be imported.',
            'unsupported_source' => 'This kind of file can\'t be imported yet.',
            'missing_file' => 'The uploaded file couldn\'t be found. Upload it again.',
            'failed' => 'Something went wrong with the import. Try again, or upload the file again.',
        ],
    ],
];
