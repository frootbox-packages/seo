<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Website;

use Frootbox\Core\Http\SiteUrl;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /sitemap.xml (decision 0223).
 */
final readonly class SitemapHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/sitemap.xml';

    public function __construct(
        private Sitemap $sitemap,
        private SiteUrl $site,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->createResponse()
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody($this->streams->createStream($this->sitemap->xml($this->site->base($request))));
    }
}
