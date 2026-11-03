<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Tests;

use Frootbox\Core\Media\CreateMedium;
use Frootbox\Core\Media\Uploads;
use Frootbox\Core\Page\LockPage;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Page\Reach;
use Frootbox\Core\Page\UpdatePage;
use Frootbox\Core\ProjectPaths;
use Frootbox\Seo\Website\RobotsHandler;
use Frootbox\Seo\Website\ShareTags;
use Frootbox\Seo\Website\Sitemap;
use Frootbox\Seo\Website\SitemapHandler;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Sitemap::class)]
#[CoversClass(SitemapHandler::class)]
#[CoversClass(RobotsHandler::class)]
#[CoversClass(ShareTags::class)]
final class WebsiteTest extends SeoTestCase
{
    public function testTheSitemapListsWhatSearchEnginesMayFind(): void
    {
        $start = $this->page(null, ['de' => 'Start', 'en' => 'Home']);
        $about = $this->page($start, ['de' => 'Über uns', 'en' => 'About us']);
        $team = $this->page($start, ['de' => 'Team']);
        $hidden = $this->page($start, ['de' => 'Intern']);
        $below = $this->page($hidden, ['de' => 'Darunter']);
        $locked = $this->page($start, ['de' => 'Gesperrt']);
        $this->runner()->run(new UpdatePage($hidden, excludeFromSearchEngines: Reach::Branch));
        $this->runner()->run(new PublishPages([$start, $about, $team, $hidden, $below, $locked]));
        $this->runner()->run(new LockPage($locked));
        $this->page($start, ['de' => 'Entwurf']);

        $response = $this->get(SitemapHandler::class)->handle(new ServerRequest('GET', 'https://example.org/sitemap.xml'));
        $xml = (string) $response->getBody();

        self::assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">', $xml);
        self::assertNotFalse(simplexml_load_string($xml), 'Well-formed.');

        preg_match_all('~<loc>([^<]+)</loc>~', $xml, $locations);
        self::assertSame([
            'https://example.org/',
            'https://example.org/en',
            'https://example.org/ueber-uns',
            'https://example.org/en/about-us',
            'https://example.org/team',
        ], $locations[1], 'No fallback to German under /en/team, no drafts, no excluded branch, no locked page.');

        self::assertStringContainsString("<loc>https://example.org/ueber-uns</loc>\n  <xhtml:link rel=\"alternate\" hreflang=\"de\" href=\"https://example.org/ueber-uns\"/>\n  <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"https://example.org/en/about-us\"/>\n  <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"https://example.org/ueber-uns\"/>", $xml);
        self::assertStringContainsString("<loc>https://example.org/team</loc>\n</url>", $xml, 'Alone in its language, without alternates.');
    }

    public function testRobotsPointToTheSitemap(): void
    {
        $response = $this->get(RobotsHandler::class)->handle(new ServerRequest('GET', 'https://example.org/robots.txt'));

        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: https://example.org/sitemap.xml\n", (string) $response->getBody());
    }

    public function testTheHeadDescribesThePageForSharing(): void
    {
        $file = $this->get(ProjectPaths::class)->var('seo-' . bin2hex(random_bytes(4)) . '.png');
        @mkdir(\dirname($file), 0o775, true);
        imagepng(imagecreatetruecolor(1600, 900), $file);
        $medium = $this->runner()->run(new CreateMedium($this->get(Uploads::class)->store($file, 'roesterei.png')))->id;

        $start = $this->page(null, ['de' => 'Start'], ['de' => 'Frisch geröstet in Münster.'], [['block' => 'acme/media/Gallery', 'fields' => ['cover' => ['media' => $medium, 'alt' => 'Die Rösterei']]]]);
        $about = $this->page($start, ['de' => 'Über uns'], ['de' => 'Wer wir sind & was wir rösten.']);
        $this->runner()->run(new PublishPages([$start, $about]));

        $html = $this->html('/');
        self::assertStringContainsString('<meta property="og:type" content="website">', $html);
        self::assertStringContainsString('<meta property="og:title" content="Start">', $html, 'Without the name of the website.');
        self::assertStringContainsString('<meta property="og:description" content="Frisch geröstet in Münster.">', $html);
        self::assertStringContainsString('<meta property="og:url" content="https://example.org/">', $html);
        self::assertStringContainsString('<meta property="og:site_name" content="Testsite">', $html);
        self::assertStringNotContainsString('og:locale', $html, '"de" alone has no region.');
        self::assertMatchesRegularExpression('~<meta property="og:image" content="https://example\.org/media/' . $medium . '/1/roesterei-1280x0-[0-9a-f]{16}\.png">~', $html, 'The first image, in the format every network reads.');
        self::assertStringContainsString('<meta property="og:image:width" content="1280">', $html);
        self::assertStringContainsString('<meta property="og:image:height" content="720">', $html);
        self::assertStringContainsString('<meta property="og:image:alt" content="Die Rösterei">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        self::assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite","name":"Testsite","url":"https://example.org/","inLanguage":"de"}</script>', $html);

        $html = $this->html('/ueber-uns');
        self::assertStringContainsString('<meta property="og:description" content="Wer wir sind &amp; was wir rösten.">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary">', $html, 'No image on the page.');
        self::assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Start","item":"https://example.org/"},{"@type":"ListItem","position":2,"name":"Über uns","item":"https://example.org/ueber-uns"}]}</script>', $html);
    }
}
