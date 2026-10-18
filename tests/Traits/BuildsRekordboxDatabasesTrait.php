<?php

namespace Tests\Traits;

use PDO;

/**
 * Builds small decrypted Rekordbox master.db files for import tests, using the same tables and
 * columns as Rekordbox 6 and 7 (only the ones the import reads)
 */
trait BuildsRekordboxDatabasesTrait
{
    /**
     * @param  array{
     *     version?: string,
     *     artists?: list<array<string, mixed>>,
     *     tracks?: list<array<string, mixed>>,
     *     keys?: list<array<string, mixed>>,
     *     albums?: list<array<string, mixed>>,
     *     genres?: list<array<string, mixed>>,
     *     labels?: list<array<string, mixed>>,
     *     tags?: list<array<string, mixed>>,
     *     track_tags?: list<array<string, mixed>>,
     *     histories?: list<array<string, mixed>>,
     *     plays?: list<array<string, mixed>>,
     * }  $data
     */
    public function makeRekordboxDatabase(array $data = []): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'master-db-');
        $pdo = new PDO('sqlite:'.$path);
        $deleted = 'rb_local_deleted TINYINT(1) DEFAULT 0';

        $pdo->exec('create table djmdProperty (DBID VARCHAR(255), DBVersion VARCHAR(255))');
        $pdo->exec("create table djmdArtist (ID VARCHAR(255) PRIMARY KEY, Name VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdKey (ID VARCHAR(255) PRIMARY KEY, ScaleName VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdAlbum (ID VARCHAR(255) PRIMARY KEY, Name VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdGenre (ID VARCHAR(255) PRIMARY KEY, Name VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdLabel (ID VARCHAR(255) PRIMARY KEY, Name VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdContent (ID VARCHAR(255) PRIMARY KEY, Title VARCHAR(255), ArtistID VARCHAR(255), AlbumID VARCHAR(255), GenreID VARCHAR(255), LabelID VARCHAR(255), KeyID VARCHAR(255), BPM INTEGER, Length INTEGER, ReleaseYear INTEGER, Commnt TEXT, DJPlayCount INTEGER, Rating INTEGER, StockDate VARCHAR(255), FolderPath VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdMyTag (ID VARCHAR(255) PRIMARY KEY, Seq INTEGER, Name VARCHAR(255), Attribute INTEGER, ParentID VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdSongMyTag (ID VARCHAR(255) PRIMARY KEY, MyTagID VARCHAR(255), ContentID VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdHistory (ID VARCHAR(255) PRIMARY KEY, Name VARCHAR(255), Attribute INTEGER, ParentID VARCHAR(255), DateCreated VARCHAR(255), {$deleted})");
        $pdo->exec("create table djmdSongHistory (ID VARCHAR(255) PRIMARY KEY, HistoryID VARCHAR(255), ContentID VARCHAR(255), TrackNo INTEGER, created_at DATETIME, {$deleted})");

        $pdo->prepare('insert into djmdProperty (DBID, DBVersion) values (?, ?)')->execute(['1', $data['version'] ?? '6000']);

        $tables = [
            'artists' => 'djmdArtist', 'tracks' => 'djmdContent', 'keys' => 'djmdKey', 'albums' => 'djmdAlbum',
            'genres' => 'djmdGenre', 'labels' => 'djmdLabel', 'tags' => 'djmdMyTag', 'track_tags' => 'djmdSongMyTag',
            'histories' => 'djmdHistory', 'plays' => 'djmdSongHistory',
        ];

        foreach ($tables as $key => $table) {
            foreach ($data[$key] ?? [] as $row) {
                $columns = array_keys($row);
                $pdo->prepare(sprintf('insert into %s (%s) values (%s)', $table, implode(', ', $columns), implode(', ', array_fill(0, count($columns), '?'))))
                    ->execute(array_values($row));
            }
        }

        return $path;
    }

    /**
     * A small library: two artists, three tracks (one deleted in Rekordbox), a tag and a history session
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function sampleRekordboxLibrary(): array
    {
        return [
            'artists' => [
                ['ID' => '11', 'Name' => 'Fred again..'],
                ['ID' => '12', 'Name' => 'Bicep'],
            ],
            'keys' => [
                ['ID' => '1', 'ScaleName' => '8A'],
                ['ID' => '2', 'ScaleName' => 'F#m'],
            ],
            'albums' => [['ID' => '21', 'Name' => 'Actual Life']],
            'genres' => [['ID' => '31', 'Name' => 'House']],
            'labels' => [['ID' => '41', 'Name' => 'Atlantic']],
            'tracks' => [
                [
                    'ID' => '101', 'Title' => 'Marea', 'ArtistID' => '11', 'AlbumID' => '21', 'GenreID' => '31',
                    'LabelID' => '41', 'KeyID' => '1', 'BPM' => 12400, 'Length' => 225, 'ReleaseYear' => 2021,
                    'Commnt' => '/* Peak */', 'DJPlayCount' => 7, 'Rating' => 4, 'StockDate' => '2024-12-25',
                    'FolderPath' => 'tidal:tracks:356883832',
                ],
                [
                    'ID' => '102', 'Title' => 'Glue', 'ArtistID' => '12', 'KeyID' => '2', 'BPM' => 12950,
                    'Length' => 269, 'FolderPath' => '/Users/dj/Music/Glue.mp3',
                ],
                ['ID' => '103', 'Title' => 'Deleted in Rekordbox', 'ArtistID' => '12', 'rb_local_deleted' => 1],
            ],
            'tags' => [
                ['ID' => '901', 'Name' => 'Mood', 'Attribute' => 1, 'ParentID' => 'root'],
                ['ID' => '902', 'Name' => 'Peak', 'Attribute' => 0, 'ParentID' => '901'],
            ],
            'track_tags' => [['ID' => '1', 'MyTagID' => '902', 'ContentID' => '101']],
            'histories' => [
                ['ID' => '700', 'Name' => '2025', 'Attribute' => 1, 'ParentID' => 'root', 'DateCreated' => '2025-09-26 18:00:00'],
                ['ID' => '701', 'Name' => 'HISTORY 2025-09-26', 'Attribute' => 0, 'ParentID' => '700', 'DateCreated' => '2025-09-26 18:59:16'],
            ],
            'plays' => [
                ['ID' => '1', 'HistoryID' => '701', 'ContentID' => '102', 'TrackNo' => 2, 'created_at' => '2025-09-26 18:05:00.000 +00:00'],
                ['ID' => '2', 'HistoryID' => '701', 'ContentID' => '101', 'TrackNo' => 1, 'created_at' => '2025-09-26 17:59:16.301 +00:00'],
            ],
        ];
    }
}
