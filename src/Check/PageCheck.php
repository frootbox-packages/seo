<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Check;

use Frootbox\Core\Page\PageStatus;

/**
 * A page in one language with what search engines see and the hints of
 * the check.
 */
final readonly class PageCheck
{
    /**
     * @param list<Hint> $hints
     */
    public function __construct(
        public string $id,
        public string $language,
        public PageStatus $status,
        /** The title of the page */
        public string $title,
        /** The title in search results, with the name of the website */
        public string $searchTitle,
        public string $description,
        /** Left out by search engines, see page.update */
        public bool $excluded,
        public array $hints,
    ) {}

    public function warnings(): int
    {
        return \count(array_filter($this->hints, static fn(Hint $hint): bool => HintLevel::Warning === $hint->level));
    }
}
