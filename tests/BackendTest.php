<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Tests;

use Frootbox\Backend\Tests\Support\BackendTestCase;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Seo\Backend\SeoHandler;
use Frootbox\Seo\SeoExtension;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SeoHandler::class)]
#[CoversClass(SeoExtension::class)]
final class BackendTest extends BackendTestCase
{
    protected function extensions(): Extensions
    {
        return new Extensions([self::backend(), ...array_values(iterator_to_array(FixtureSite::extensions('base', 'site'))), SeoTestCase::package()], 'seo-backend');
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    public function testEditorsSeeTheHintsOfEveryPage(): void
    {
        $start = $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => 'Start', 'description' => 'Frisch geröstete Bohnen aus Münster, jede Woche neu, im Laden und online.']]), Context::system())->id;
        $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => 'Über uns'], 'en' => ['title' => 'About us']], $start), Context::system());
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        $html = $this->html($this->request('GET', '/cms/seo', session: $session));

        self::assertStringContainsString('href="/cms/seo" aria-current="page"', $html, 'The sidebar has the screen.');
        self::assertStringContainsString('<h2 class="fb-heading-4" id="seo-de-title">Deutsch</h2>', $html);
        self::assertStringContainsString('Keine Beschreibung. Suchmaschinen zeigen dann irgendeinen Text der Seite.', $html);
        self::assertStringContainsString('1 Seite ohne Hinweise', $html);
        self::assertStringContainsString('>About us</a>', $html);
        self::assertStringContainsString('href="/sitemap.xml"', $html);

        $page = $this->html($this->request('GET', '/cms/pages/' . $start, session: $session));
        self::assertStringContainsString('name="translations.de.description"', $page, 'The description is written in the settings of the page.');
        self::assertStringContainsString('Frisch geröstete Bohnen aus Münster, jede Woche neu, im Laden und online.</textarea>', $page);
        self::assertMatchesRegularExpression('~<input[^>]+name="translations\.de\.seoTitle"[^>]+placeholder="Testsite"~', $page, 'The title for search engines shows the one that holds without it.');

        $this->action('page.update', ['id' => $start, 'translations' => ['de' => ['title' => 'Start', 'description' => 'Neu geschrieben, damit Suchmaschinen und Netzwerke einen guten Text zeigen.']]], $session);
        self::assertStringContainsString('Neu geschrieben, damit Suchmaschinen', $this->html($this->request('GET', '/cms/seo', session: $session)), 'The check reads the draft.');

        $this->createUser('nobody@example.org', []);
        self::assertSame(403, $this->request('GET', '/cms/seo', session: $this->signIn('nobody@example.org'))->getStatusCode());
    }
}
