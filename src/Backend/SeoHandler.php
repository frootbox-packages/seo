<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Language\Languages;
use Frootbox\Seo\Check\CheckPages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The screen "SEO": the check of every page by language (decision 0223).
 */
final readonly class SeoHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/seo/page.check';

    public function __construct(
        private BackendView $view,
        private Runner $runner,
        private Languages $languages,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $groups = [];
        $warnings = 0;

        foreach ($this->languages->codes() as $code) {
            $groups[$code] = ['language' => $this->languages->get($code), 'hints' => [], 'fine' => []];
        }

        foreach ($this->runner->ask(new CheckPages()) as $check) {
            $groups[$check->language][[] === $check->hints ? 'fine' : 'hints'][] = $check;
            $warnings += $check->warnings();
        }

        return $this->view->render($request, '@frootbox/seo/backend/seo.twig', [
            'groups' => array_values($groups),
            'warnings' => $warnings,
        ]);
    }
}
