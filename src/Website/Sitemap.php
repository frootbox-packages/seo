<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Website;

use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\PageOutline;
use Frootbox\Core\Url\Addresses;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;

/**
 * The sitemap (decision 0223): every current address of a published page
 * or object in every language, taken from the table urls. Left out are
 * addresses of a fallback, locked pages and pages excluded from search
 * engines, each with the objects below them.
 */
final readonly class Sitemap
{
    /** More are not allowed in one file. */
    public const int MAX = 50000;

    public function __construct(
        private Addresses $addresses,
        private PageOutline $outline,
        private Urls $urls,
        private Languages $languages,
    ) {}

    /**
     * The URLs by target, each with its URL in every language.
     *
     * @return array<string, array<string, string>> URL by language by target
     */
    public function entries(?string $site): array
    {
        $entries = [];
        $count = 0;

        foreach ($this->addresses->fetch(['where' => ['current' => true, 'fallback' => false], 'order' => 'id']) as $address) {
            $target = Target::tryParse($address->target);
            $page = $address->basePageId ?? (null !== $target && $target->isPage() ? $target->id : null);

            if (null === $target || null === $page || !$this->visible($page) || !$this->languages->has($address->language)) {
                continue;
            }

            $url = $this->urls->absolute($target, $address->language, $site);

            if (null === $url || $count >= self::MAX) {
                continue;
            }

            $entries[$address->target][$address->language] = $url;
            ++$count;
        }

        return $entries;
    }

    public function xml(?string $site): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ];

        foreach ($this->entries($site) as $urls) {
            foreach ($urls as $url) {
                $lines[] = '<url>';
                $lines[] = '  <loc>' . self::escape($url) . '</loc>';

                if (\count($urls) > 1) {
                    foreach ($urls as $language => $alternate) {
                        $lines[] = \sprintf('  <xhtml:link rel="alternate" hreflang="%s" href="%s"/>', self::escape($language), self::escape($alternate));
                    }

                    $lines[] = \sprintf('  <xhtml:link rel="alternate" hreflang="x-default" href="%s"/>', self::escape($urls[$this->languages->default()->code] ?? reset($urls)));
                }

                $lines[] = '</url>';
            }
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines) . "\n";
    }

    private function visible(string $page): bool
    {
        return null !== $this->outline->get($page)
            && null === $this->outline->lockedBy($page)
            && null === $this->outline->excludedFromSearchEnginesBy($page)
            // A page with a password is not for search engines (decision 0358).
            && null === $this->outline->protectedBy($page);
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_XML1 | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
