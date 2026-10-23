<?php

namespace Database\Seeders;

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\FileManager\FileManagerStorage;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Nested folders with placeholder images and PDFs (#168), written to the tenant's disk so previews,
 * the file picker, the theme's logo and the CMS's images all work. Images are drawn with GD, and
 * everything is stored through the real FileManagerStorage, as an upload would be.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=FileManagerSeeder`. Safe to re-run:
 * nothing is seeded when the tenant has any files or folders.
 */
class FileManagerSeeder extends Seeder
{
    /**
     * Placeholder images by path: label, width, height, colour and alt text. The first is the one
     * the CMS seeder uses for its hero and OG images.
     *
     * @var array<string, array{0: string, 1: int, 2: int, 3: string, 4: string}>
     */
    public const array IMAGES = [
        'images/hero.jpg' => ['Northside Sound', 1600, 900, '#4C1D95', 'A packed dance floor under purple lights'],
        'images/gigs/rooftop-party.jpg' => ['Rooftop Party', 1200, 800, '#C2410C', 'A rooftop party at sunset'],
        'images/gigs/wedding-barn.jpg' => ['Wedding', 1200, 800, '#0F766E', 'A barn wedding reception with fairy lights'],
        'images/gigs/club-night.jpg' => ['Club Night', 1200, 800, '#1E3A8A', 'The DJ booth at a club night'],
        'images/team/dj-portrait.jpg' => ['DJ', 800, 1000, '#334155', 'The DJ behind the decks'],
        'branding/logo.png' => ['NORTHSIDE SOUND', 600, 160, '#7C3AED', 'Northside Sound'],
        'branding/favicon.png' => ['NS', 64, 64, '#7C3AED', 'Northside Sound'],
    ];

    /**
     * PDFs by path: a title and lines of text
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const array DOCUMENTS = [
        'documents/price-list-2026.pdf' => ['Price list 2026', [
            'Weddings: from 950 GBP, including ceremony and evening sets',
            'Parties: from 450 GBP for up to five hours',
            'Club nights: on request',
        ]],
        'documents/wedding-planner.pdf' => ['Wedding music planner', [
            'First dance:',
            'Must plays:',
            'Do not plays:',
        ]],
    ];

    /** @var array<string, Folder> */
    protected array $folders = [];

    public function __construct(protected FileManagerStorage $storage) {}

    public function run(): void
    {
        if (File::query()->exists() || Folder::query()->exists()) {
            return;
        }

        foreach (self::IMAGES as $path => [$label, $width, $height, $colour, $alt]) {
            $this->store($path, $this->image($label, $width, $height, $colour, str_ends_with($path, '.png')), $alt);
        }

        foreach (self::DOCUMENTS as $path => [$title, $lines]) {
            $this->store($path, $this->pdf($title, $lines));
        }
    }

    /**
     * Stores the contents at the path as an upload, replacing any file an earlier reset left there
     */
    protected function store(string $path, string $contents, ?string $alt = null): File
    {
        $this->storage->disk()->delete($path);

        $temporary = (string) tempnam(sys_get_temp_dir(), 'seed-');
        file_put_contents($temporary, $contents);

        $file = $this->storage->storeUpload(new UploadedFile($temporary, basename($path), null, null, true), $this->folder(dirname($path)));
        $file->update(['alt_text' => $alt]);

        unlink($temporary);

        return $file;
    }

    /**
     * The folder at the path, made (with its parents) the first time it's needed
     */
    protected function folder(string $path): ?Folder
    {
        if ($path === '.') {
            return null;
        }

        return $this->folders[$path] ??= $this->storage->createFolder(basename($path), $this->folder(dirname($path)));
    }

    /**
     * A placeholder image in the colour, with its label, as a PNG or JPEG
     */
    protected function image(string $label, int $width, int $height, string $colour, bool $png): string
    {
        $image = imagecreatetruecolor($width, $height);
        [$red, $green, $blue] = array_map(hexdec(...), str_split(ltrim($colour, '#'), 2));
        $background = (int) imagecolorallocate($image, (int) $red, (int) $green, (int) $blue);
        imagefill($image, 0, 0, $background);

        $this->drawLabel($image, $label, $background);

        // A lighter band across it, so it isn't flat
        imagefilledpolygon($image, [
            (int) ($width * 0.55), 0,
            $width, 0,
            (int) ($width * 0.45), $height,
            0, $height,
        ], (int) imagecolorallocatealpha($image, 255, 255, 255, 112));

        ob_start();
        $png ? imagepng($image) : imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * The label in GD's built-in font, scaled up to fill about half the image's width
     */
    protected function drawLabel(GdImage $image, string $label, int $background): void
    {
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);

        $text = imagecreatetruecolor($textWidth, $textHeight);
        imagefill($text, 0, 0, $background);
        imagestring($text, $font, 0, 0, $label, (int) imagecolorallocate($text, 255, 255, 255));

        $scale = max(1, (int) floor(min(imagesx($image) * 0.6 / $textWidth, imagesy($image) * 0.5 / $textHeight)));
        $scaledWidth = $textWidth * $scale;
        $scaledHeight = $textHeight * $scale;

        imagecopyresized(
            $image, $text,
            (int) ((imagesx($image) - $scaledWidth) / 2), (int) ((imagesy($image) - $scaledHeight) / 2),
            0, 0, $scaledWidth, $scaledHeight, $textWidth, $textHeight,
        );
    }

    /**
     * A one-page PDF with the title and lines of text in Helvetica
     *
     * @param  list<string>  $lines
     */
    protected function pdf(string $title, array $lines): string
    {
        $escape = fn (string $text) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = 'BT /F1 22 Tf 72 740 Td ('.$escape($title).') Tj /F1 12 Tf';

        foreach ($lines as $line) {
            $stream .= ' 0 -28 Td ('.$escape($line).') Tj';
        }

        $stream .= ' ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
