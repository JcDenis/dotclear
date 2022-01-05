<?php
/**
 * @brief Dotclear core static trait
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception\CoreException;
use Dotclear\Core\Core;

trait StaticCore
{
    /** @var Core Core instance */
    protected static $core;

    public static function setCore(Core $core): void
    {
        self::$core = $core;
    }

    protected static function getCore(): Core
    {
        if (is_a(self::$core, 'Core')) {
            throw new CoreException('No Core instance');
        }

        return self::$core;
    }
}
