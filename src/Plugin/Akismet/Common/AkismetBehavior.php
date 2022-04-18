<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Akismet\Common;

use ArrayObject;

/**
 * Akismet behaviors.
 *
 * \Dotclear\Plugin\Akismet\Common\AkismetBehavior
 *
 * @ingroup  Plugin Akismet
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
