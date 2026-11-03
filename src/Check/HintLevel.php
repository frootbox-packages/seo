<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Seo\Check;

enum HintLevel: string
{
    /** Worth fixing: search engines show something else or nothing */
    case Warning = 'warning';

    /** Good to know, e.g. a description a bit too long */
    case Notice = 'notice';
}
