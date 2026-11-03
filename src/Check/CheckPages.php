<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Check;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Config\Config;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\PageNode;
use Frootbox\Core\Page\PageReader;
use Frootbox\Core\Page\TranslationData;
use Frootbox\Database\Ulid;

/**
 * The check of every page (decision 0223), as editors see the pages:
 * drafts first. Pages with warnings come first.
 *
 * @implements Query<list<PageCheck>>
 */
#[AsQuery('frootbox/seo/page.check', 'Checks title and description of the pages for search engines: missing, too short, too long or the same as on another page')]
final readonly class CheckPages implements Query
{
    /** Search engines cut longer titles, about 600 pixels. */
    public const int TITLE_MAX = 60;

    public const int DESCRIPTION_MIN = 50;

    public const int DESCRIPTION_MAX = 160;

    public function __construct(
        #[Description('Only this page; left out for all pages')]
        #[Pattern(Ulid::PATTERN)]
        public ?string $id = null,
        #[Description('Only this language; left out for all languages')]
        public ?string $language = null,
    ) {}

    /**
     * @return list<PageCheck>
     */
    public function handle(PageReader $reader, Languages $languages, Config $config): array
    {
        $nodes = $reader->nodes();
        $visibility = $reader->visibility($nodes);
        $site = $config->string('app.name', 'Frootbox');
        $checks = [];

        foreach ($languages->codes() as $language) {
            if (null !== $this->language && $language !== $this->language) {
                continue;
            }

            $pages = [];

            foreach ($nodes as $id => $node) {
                $translation = $node->data->translations[$language] ?? null;

                if (null !== $translation) {
                    $pages[$id] = [$node, $translation];
                }
            }

            $titles = self::count(array_map(static fn(array $page): string => $page[1]->title, $pages));
            $descriptions = self::count(array_map(static fn(array $page): string => $page[1]->description, $pages));

            foreach ($pages as $id => [$node, $translation]) {
                if (null !== $this->id && $id !== $this->id) {
                    continue;
                }

                $excluded = null !== ($visibility[$id] ?? null)?->excludedFromSearchEnginesBy;
                $searchTitle = null === $node->data->parentId ? $site : $translation->title . ' – ' . $site;
                $hints = $excluded
                    ? [new Hint('excluded', HintLevel::Notice, 'Excluded from search engines, so it is not in sitemap.xml either.')]
                    : self::hints($id, $searchTitle, $translation->title, $translation->description, $titles, $descriptions, $pages);

                $checks[] = new PageCheck($id, $language, $node->status, $translation->title, $searchTitle, $translation->description, $excluded, $hints);
            }
        }

        usort($checks, static fn(PageCheck $a, PageCheck $b): int => [-$a->warnings(), -\count($a->hints), mb_strtolower($a->title), $a->id] <=> [-$b->warnings(), -\count($b->hints), mb_strtolower($b->title), $b->id]);

        return $checks;
    }

    /**
     * @param array<string, list<string>> $titles Ids by normalized title
     * @param array<string, list<string>> $descriptions Ids by normalized description
     * @param array<string, array{0: PageNode, 1: TranslationData}> $pages
     *
     * @return list<Hint>
     */
    private static function hints(string $id, string $searchTitle, string $title, string $description, array $titles, array $descriptions, array $pages): array
    {
        $hints = [];
        $length = mb_strlen($description);

        if (0 === $length) {
            $hints[] = new Hint('description_missing', HintLevel::Warning, 'No description. Search engines then show some text of the page.');
        } elseif ($length < self::DESCRIPTION_MIN) {
            $hints[] = new Hint('description_short', HintLevel::Notice, 'The description has only {count} characters. 50 to 160 fit best.', ['count' => $length]);
        } elseif ($length > self::DESCRIPTION_MAX) {
            $hints[] = new Hint('description_long', HintLevel::Notice, 'The description has {count} characters. From about 160, search engines cut it.', ['count' => $length]);
        }

        if (mb_strlen($searchTitle) > self::TITLE_MAX) {
            $hints[] = new Hint('title_long', HintLevel::Notice, 'The title in search results has {count} characters with the name of the website. From about 60, search engines cut it.', ['count' => mb_strlen($searchTitle)]);
        }

        $other = self::other($id, $titles[self::key($title)] ?? []);

        if (null !== $other) {
            $hints[] = new Hint('title_duplicate', HintLevel::Warning, 'The same title as the page "{page}".', ['page' => $pages[$other][1]->title]);
        }

        $other = 0 === $length ? null : self::other($id, $descriptions[self::key($description)] ?? []);

        if (null !== $other) {
            $hints[] = new Hint('description_duplicate', HintLevel::Warning, 'The same description as the page "{page}".', ['page' => $pages[$other][1]->title]);
        }

        return $hints;
    }

    /**
     * @param array<string, string> $texts Text by id
     *
     * @return array<string, list<string>> Ids by normalized text
     */
    private static function count(array $texts): array
    {
        $ids = [];

        foreach ($texts as $id => $text) {
            if ('' !== trim($text)) {
                $ids[self::key($text)][] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     */
    private static function other(string $id, array $ids): ?string
    {
        foreach ($ids as $other) {
            if ($other !== $id) {
                return $other;
            }
        }

        return null;
    }

    private static function key(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
