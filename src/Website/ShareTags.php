<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Website;

use Frootbox\Core\Media\Media;
use Frootbox\Core\Media\MediaUrls;
use Frootbox\Core\Navigation\Menus;
use Frootbox\Core\View\BuildingHead;
use Frootbox\Core\View\Head;
use Frootbox\RichText\Html;

/**
 * Open Graph, the Twitter card and structured data in the head of every
 * page and object (decision 0223). Pages without a canonical address,
 * such as error pages, get none.
 */
final readonly class ShareTags
{
    /** Wide enough for every network, from the ladder of MediaUrls */
    public const int IMAGE_WIDTH = 1280;

    public function __construct(
        private Media $media,
        private MediaUrls $urls,
        private Menus $menus,
    ) {}

    public function __invoke(BuildingHead $event): void
    {
        $head = $event->head;

        if (null === $head->canonical || null === $head->name) {
            return;
        }

        $origin = self::origin($head->canonical);
        $description = trim((string) preg_replace('/\s+/u', ' ', $head->description));
        $image = $this->image($head, $origin);

        $properties = [
            'og:type' => 'website',
            'og:title' => $head->name,
            'og:description' => $description,
            'og:url' => $head->canonical,
            'og:site_name' => $head->site ?? '',
            // Only with a region, e.g. de_AT; "de" alone is not a locale.
            'og:locale' => str_contains($head->language, '-') ? str_replace('-', '_', $head->language) : '',
            'og:image' => $image['url'] ?? '',
            'og:image:width' => isset($image['width']) ? (string) $image['width'] : '',
            'og:image:height' => isset($image['height']) ? (string) $image['height'] : '',
            'og:image:alt' => $image['alt'] ?? '',
        ];

        foreach ($properties as $property => $content) {
            if ('' !== $content) {
                $head->add(Html::open('meta', ['property' => $property, 'content' => $content]));
            }
        }

        $head->add(Html::open('meta', ['name' => 'twitter:card', 'content' => null === $image ? 'summary' : 'summary_large_image']));

        $data = $head->start ? $this->website($head) : $this->breadcrumb($origin);

        if (null !== $data) {
            $head->add('<script type="application/ld+json">' . json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_THROW_ON_ERROR) . '</script>');
        }
    }

    /**
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    private function image(Head $head, string $origin): ?array
    {
        $medium = null === $head->image ? null : $this->media->fetchById($head->image['media']);

        // Networks show neither SVG nor videos as a preview.
        if (null === $medium || !$medium->isImage() || $medium->isVector() || $medium->width < 1 || $medium->height < 1) {
            return null;
        }

        // The largest step of the ladder up to the wanted width, never larger than the original.
        $width = MediaUrls::WIDTHS[0];

        foreach (MediaUrls::WIDTHS as $step) {
            if ($step <= min(self::IMAGE_WIDTH, $medium->width)) {
                $width = $step;
            }
        }

        $formats = $this->urls->formats($medium);
        // The fallback format, JPEG or PNG, comes last: not every network reads AVIF or WebP.
        $url = $this->urls->url($medium, $width, 0, array_pop($formats) ?? 'jpg');

        return [
            // A public bucket has its own host (decision 0268).
            'url' => MediaUrls::isAbsolute($url) ? $url : $origin . $url,
            'width' => $width,
            'height' => (int) round($medium->height * $width / $medium->width),
            'alt' => $head->image['alt'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function website(Head $head): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $head->site,
            'url' => $head->canonical,
            'inLanguage' => $head->language,
        ], static fn(mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function breadcrumb(string $origin): ?array
    {
        $items = [];

        foreach ($this->menus->breadcrumb() as $item) {
            if (null === $item->url) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => \count($items) + 1,
                'name' => $item->label,
                'item' => str_starts_with($item->url, '/') ? $origin . $item->url : $item->url,
            ];
        }

        return \count($items) < 2 ? null : [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * "https://example.org" from "https://example.org/about".
     */
    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $port;
    }
}
