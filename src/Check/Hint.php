<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Check;

/**
 * One finding of the check (decision 0223). The message is English with
 * placeholders, the backend translates it.
 */
final readonly class Hint
{
    /**
     * @param array<string, string|int> $params
     */
    public function __construct(
        /** e.g. "description_missing" */
        public string $code,
        public HintLevel $level,
        public string $message,
        public array $params = [],
    ) {}
}
