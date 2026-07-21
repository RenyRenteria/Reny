<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

class CommunityPostContent
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'a',
        'b',
        'blockquote',
        'br',
        'div',
        'em',
        'h2',
        'h3',
        'i',
        'li',
        'ol',
        'p',
        's',
        'strong',
        'u',
        'ul',
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return collect(preg_split('/\R{2,}/', $html) ?: [])
                ->map(fn (string $paragraph): string => '<p>'.nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>')
                ->implode('');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="community-post-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('community-post-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        return collect(iterator_to_array($root->childNodes))
            ->map(fn (DOMNode $node): string => (string) $document->saveHTML($node))
            ->implode('');
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, array{type:string,url:string,embed_url?:string,label:string}>
     */
    public static function normalizeMediaUrls(array $values): array
    {
        return collect($values)
            ->flatMap(function (mixed $value): array {
                if (is_array($value) && is_string($value['url'] ?? null)) {
                    return [$value['url']];
                }

                return is_string($value) ? (preg_split('/\R/', $value) ?: []) : [];
            })
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->take(12)
            ->map(fn (string $url): ?array => self::mediaItem($url))
            ->filter()
            ->values()
            ->all();
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, ['script', 'style'], true)) {
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }

                $parent->removeChild($node);

                continue;
            }

            $href = $tag === 'a' ? self::safeUrl($node->getAttribute('href')) : null;

            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                $node->removeAttribute($attribute->name);
            }

            if ($tag !== 'a') {
                continue;
            }

            if ($href === null) {
                continue;
            }

            $node->setAttribute('href', $href);
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * @return array{type:string,url:string,embed_url?:string,label:string}|null
     */
    private static function mediaItem(string $url): ?array
    {
        $url = self::safeUrl($url);

        if ($url === null) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $label = $host !== '' ? preg_replace('/^www\./', '', $host) : 'Abrir enlace';

        if ($youtubeId = self::youtubeId($url, $host, $path)) {
            return [
                'type' => 'embed',
                'url' => $url,
                'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$youtubeId,
                'label' => 'YouTube',
            ];
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('~/(\d+)~', $path, $matches)) {
            return [
                'type' => 'embed',
                'url' => $url,
                'embed_url' => 'https://player.vimeo.com/video/'.$matches[1],
                'label' => 'Vimeo',
            ];
        }

        if ($host === 'open.spotify.com' && preg_match('~^/(track|album|playlist|episode|show)/([A-Za-z0-9]+)~', $path, $matches)) {
            return [
                'type' => 'embed',
                'url' => $url,
                'embed_url' => 'https://open.spotify.com/embed/'.$matches[1].'/'.$matches[2],
                'label' => 'Spotify',
            ];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return [
            'type' => match (true) {
                in_array($extension, ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'], true) => 'image',
                in_array($extension, ['m4v', 'mp4', 'ogg', 'webm'], true) => 'video',
                in_array($extension, ['aac', 'm4a', 'mp3', 'oga', 'wav'], true) => 'audio',
                default => 'link',
            },
            'url' => $url,
            'label' => (string) $label,
        ];
    }

    private static function safeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
    }

    private static function youtubeId(string $url, string $host, string $path): ?string
    {
        $id = null;

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $id = $query['v'] ?? null;

            if (! $id && preg_match('~^/(?:embed|shorts)/([^/?]+)~', $path, $matches)) {
                $id = $matches[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)
            ? Str::limit($id, 20, '')
            : null;
    }
}
