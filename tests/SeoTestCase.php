<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Tests;

use Frootbox\Core\Config\Environment;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Nyholm\Psr7\ServerRequest;

abstract class SeoTestCase extends NotesTestCase
{
    public const string ROOT = __DIR__ . '/..';

    public static function package(): ExtensionPackage
    {
        return ExtensionPackage::fromManifest('frootbox/seo', (string) realpath(self::ROOT), psr4: ['Frootbox\Seo\\' => ['src/'], 'Frootbox\Seo\Migrations\\' => ['migrations/']]);
    }

    protected function environment(): Environment
    {
        return Environment::Production;
    }

    protected function extensions(): Extensions
    {
        return new Extensions([...FixtureSite::extensions('base', 'site', 'media')->packages, self::package()], 'seo');
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    /**
     * A page in each given language, e.g. ["de" => "Über uns"].
     *
     * @param array<string, string>      $titles
     * @param array<string, string>      $descriptions
     * @param list<array<string, mixed>> $blocks
     */
    protected function page(?string $parentId, array $titles, array $descriptions = [], array $blocks = []): string
    {
        $translations = [];

        foreach ($titles as $language => $title) {
            $translations[$language] = ['title' => $title, 'description' => $descriptions[$language] ?? '', 'content' => ['fields' => ['heading' => $title], 'areas' => ['main' => $blocks]]];
        }

        return $this->runner()->run(new CreatePage('acme/base/Page', $translations, $parentId))->id;
    }

    protected function html(string $path): string
    {
        return (string) $this->get(PageHandler::class)->handle(new ServerRequest('GET', 'https://example.org' . $path))->getBody();
    }
}
