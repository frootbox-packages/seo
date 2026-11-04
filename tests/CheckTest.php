<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Tests;

use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Page\Reach;
use Frootbox\Core\Page\UpdatePage;
use Frootbox\Seo\Check\CheckPages;
use Frootbox\Seo\Check\Hint;
use Frootbox\Seo\Check\PageCheck;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CheckPages::class)]
#[CoversClass(PageCheck::class)]
#[CoversClass(Hint::class)]
final class CheckTest extends SeoTestCase
{
    public function testFindsMissingLongAndRepeatedTexts(): void
    {
        $good = 'Frisch geröstete Bohnen aus Münster, jede Woche neu, im Laden und online.';
        $start = $this->page(null, ['de' => 'Start', 'en' => 'Home'], ['de' => $good]);
        $shop = $this->page($start, ['de' => 'Kaffee'], ['de' => 'Kurz.']);
        $courses = $this->page($start, ['de' => 'Kurse'], ['de' => str_repeat('Lang ', 40)]);
        $old = $this->page($start, ['de' => 'Kaffee aus aller Welt, frisch geröstet und schnell geliefert'], ['de' => $good]);
        $this->runner()->run(new PublishPages([$start, $shop, $courses]));
        $hidden = $this->page($start, ['de' => 'Intern']);
        $this->runner()->run(new UpdatePage($hidden, excludeFromSearchEngines: Reach::Page));

        $checks = [];

        foreach ($this->runner()->ask(new CheckPages()) as $check) {
            $checks[$check->language . ' ' . $check->title] = array_map(static fn(Hint $hint): string => $hint->code, $check->hints);
        }

        self::assertSame([
            'de Kaffee aus aller Welt, frisch geröstet und schnell geliefert' => ['title_long', 'description_duplicate'],
            'en Home' => ['description_missing'],
            'de Start' => ['description_duplicate'],
            'de Intern' => ['excluded'],
            'de Kaffee' => ['description_short'],
            'de Kurse' => ['description_long'],
        ], $checks, 'Warnings first, then by title; an excluded page needs nothing else.');

        $check = $this->runner()->ask(new CheckPages($shop, 'de'))[0];
        self::assertSame('Kaffee – Testsite', $check->searchTitle);
        self::assertSame(['count' => 5], $check->hints[0]->params);
        self::assertSame('published', $check->status->value);
        self::assertCount(1, $this->runner()->ask(new CheckPages($old)), 'Drafts count, as editors see them.');
    }

    public function testTheSameTitleInAnotherBranchIsNamed(): void
    {
        $start = $this->page(null, ['de' => 'Start'], ['de' => 'Die Startseite der Rösterei mit allem, was es Neues gibt.']);
        $shop = $this->page($start, ['de' => 'Laden'], ['de' => 'Unser Laden in der Altstadt mit Kaffee, Kuchen und Kursen.']);
        $this->page($start, ['de' => 'Kontakt'], ['de' => 'So erreichst Du uns per Telefon, per Mail oder im Laden.']);
        $this->page($shop, ['de' => 'Kontakt'], ['de' => 'Der Laden hat eigene Zeiten und eine eigene Telefonnummer.']);

        $hints = array_values(array_filter($this->runner()->ask(new CheckPages()), static fn(PageCheck $check): bool => [] !== $check->hints));

        self::assertCount(2, $hints);
        self::assertSame('title_duplicate', $hints[0]->hints[0]->code);
        self::assertSame(['page' => 'Kontakt'], $hints[0]->hints[0]->params);
    }

    public function testATitleOfItsOwnIsWhatCounts(): void
    {
        $start = $this->page(null, ['de' => 'Start'], ['de' => 'Die Startseite der Rösterei mit allem, was es Neues gibt.']);
        $shop = $this->page($start, ['de' => 'Laden'], ['de' => 'Unser Laden in der Altstadt mit Kaffee, Kuchen und Kursen.']);
        $courses = $this->page($start, ['de' => 'Kurse'], ['de' => 'Kurse für Siebträger, Filter und Latte Art, jeden Monat neu.']);
        $this->runner()->run(new UpdatePage($shop, ['de' => ['seoTitle' => 'Kaffee in Münster']]));
        $this->runner()->run(new UpdatePage($courses, ['de' => ['seoTitle' => 'Kaffee in Münster: Kurse für Siebträger, Filter und Latte Art jeden Monat']]));

        $checks = [];

        foreach ($this->runner()->ask(new CheckPages()) as $check) {
            $checks[$check->title] = [$check->searchTitle, $check->seoTitle, array_map(static fn(Hint $hint): string => $hint->code, $check->hints)];
        }

        self::assertSame(['Kaffee in Münster', 'Kaffee in Münster', []], $checks['Laden'], 'Exactly the title of its own.');
        self::assertSame(['title_long'], $checks['Kurse'][2], 'Measured without the name of the website.');
        self::assertSame(['Testsite', '', []], $checks['Start']);

        $this->runner()->run(new UpdatePage($courses, ['de' => ['seoTitle' => 'kaffee in  Münster']]));
        $hints = $this->runner()->ask(new CheckPages($courses))[0]->hints;
        self::assertSame('title_duplicate', $hints[0]->code, 'The same in search results, though the pages have other titles.');
        self::assertSame(['page' => 'Laden'], $hints[0]->params);
    }
}
