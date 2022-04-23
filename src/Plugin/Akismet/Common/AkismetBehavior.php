<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

// Dotclear\Plugin\Akismet\Common\AkismetBehavior
use ArrayObject;

/**
 * Akismet behaviors.
 *
 * @ingroup  Plugin Akismet Behavior
 */
class AkismetBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('antispamInitFilters', function (ArrayObject $spamfilters): void {
            $spamfilters[] = __NAMESPACE__ . '\\FilterAkismet';
        });
    }
}
