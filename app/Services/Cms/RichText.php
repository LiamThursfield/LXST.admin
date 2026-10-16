<?php

namespace App\Services\Cms;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Tiptap\Editor;
use Tiptap\Extensions\StarterKit;
use Tiptap\Marks\Link;
use Tiptap\Marks\Underline;
use Tiptap\Nodes\Image;

/**
 * Rich text content is stored twice: the editor's TipTap JSON (the source, which the editor loads
 * exactly) and HTML built from it here and sanitised, so headless sites can use either.
 */
class RichText
{
    /**
     * The TipTap nodes the editor can produce (see RichTextContentField.vue)
     *
     * @var list<string>
     */
    public const array NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem',
        'blockquote', 'hardBreak', 'horizontalRule', 'image',
    ];

    /**
     * The TipTap marks the editor can produce
     *
     * @var list<string>
     */
    public const array MARKS = ['bold', 'italic', 'underline', 'strike', 'link'];

    /**
     * Problems with a document: a node or mark the editor can't produce, or an unsafe link or image
     * URL. Sites that render the JSON themselves get the same guarantees as the HTML.
     *
     * @return list<string>
     */
    public function problems(mixed $document): array
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            return [$this->message('invalid')];
        }

        $problems = [];
        $this->checkNode($document, $problems);

        return array_values(array_unique($problems));
    }

    /**
     * Whether the document has no text and no images, e.g. a single empty paragraph
     *
     * @param  array<string, mixed>  $document
     */
    public function isEmpty(array $document): bool
    {
        $hasContent = false;

        array_walk_recursive($document, function (mixed $value, string|int $key) use (&$hasContent) {
            if (($key === 'text' && is_string($value) && trim($value) !== '') || ($key === 'type' && $value === 'image')) {
                $hasContent = true;
            }
        });

        return ! $hasContent;
    }

    /**
     * The document as sanitised HTML
     *
     * @param  array<string, mixed>  $document
     */
    public function toHtml(array $document): string
    {
        $html = (new Editor([
            'extensions' => [
                new StarterKit(['codeBlock' => false, 'code' => false]),
                new Underline,
                new Link(['HTMLAttributes' => ['rel' => 'noopener noreferrer nofollow']]),
                new Image,
            ],
        ]))->setContent($document)->getHTML();

        return $this->sanitizer()->sanitize($html);
    }

    /**
     * Only the elements and attributes the editor produces, with safe URL schemes
     */
    protected function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ul')
            ->allowElement('ol', ['start'])
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'title'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias();

        return new HtmlSanitizer($config);
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $problems
     */
    protected function checkNode(array $node, array &$problems): void
    {
        $type = $node['type'] ?? null;

        if (! in_array($type, self::NODES, true)) {
            $problems[] = $this->message('unsupported', ['type' => is_string($type) ? $type : '?']);

            return;
        }

        if ($type === 'image' && ! $this->isSafeUrl($node['attrs']['src'] ?? null, ['http', 'https'])) {
            $problems[] = $this->message('unsafe_image');
        }

        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! in_array($markType, self::MARKS, true)) {
                $problems[] = $this->message('unsupported', ['type' => is_string($markType) ? $markType : '?']);
            } elseif ($markType === 'link' && ! $this->isSafeUrl($mark['attrs']['href'] ?? null, ['http', 'https', 'mailto', 'tel'])) {
                $problems[] = $this->message('unsafe_link');
            }
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $this->checkNode($child, $problems);
            }
        }
    }

    /**
     * A URL with one of the schemes, or a path or anchor on the site
     *
     * @param  list<string>  $schemes
     */
    protected function isSafeUrl(mixed $url, array $schemes): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, $schemes, true);
    }

    /**
     * A validation message from lang/en/cms.php
     *
     * @param  array<string, string>  $replace
     */
    protected function message(string $key, array $replace = []): string
    {
        $message = __("cms.content.rich_text.{$key}", $replace);

        return is_string($message) ? $message : $key;
    }
}
