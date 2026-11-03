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
 * GET /robots.txt (decision 0223): everything allowed, with the address
 * of the sitemap. While the website is protected by a password, the core
 * answers with "Disallow: /" before this (decision 0153). A real file
 * public/robots.txt is delivered by the web server instead.
 */
final readonly class RobotsHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/robots.txt';

    public function __construct(
        private SiteUrl $site,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $lines = ['User-agent: *', 'Allow: /'];
        $base = $this->site->base($request);

        if (null !== $base) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $base . SitemapHandler::ROUTE;
        }

        return $this->responses->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody($this->streams->createStream(implode("\n", $lines) . "\n"));
    }
}
