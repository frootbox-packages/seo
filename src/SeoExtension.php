<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo;

use Frootbox\Backend\Screen;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\View\BuildingHead;
use Frootbox\Seo\Backend\SeoHandler;
use Frootbox\Seo\Check\CheckPages;
use Frootbox\Seo\Website\RobotsHandler;
use Frootbox\Seo\Website\ShareTags;
use Frootbox\Seo\Website\SitemapHandler;

/**
 * SEO (decision 0223): sitemap, robots.txt, Open Graph, structured data
 * and the check of every page. The description of a page belongs to the
 * core.
 */
final class SeoExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->query(CheckPages::class);
        $registry->listen(BuildingHead::class, ShareTags::class);
        $registry->get(SitemapHandler::ROUTE, SitemapHandler::class, 'frootbox.seo.sitemap');
        $registry->get(RobotsHandler::ROUTE, RobotsHandler::class, 'frootbox.seo.robots');

        if (class_exists(Screen::class)) {
            $registry->mount('backend.path', '/cms')
                ->get('/seo', SeoHandler::class, 'frootbox.seo.seo');
            $registry->add(Screen::class, new Screen('SEO', 'frootbox.seo.seo', Screen::WEBSITE, 'search', SeoHandler::PERMISSION, order: 35));
        }
    }
}
