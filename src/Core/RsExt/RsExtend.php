<?php
/**
 * @class Dotclear\Core\RsExtend
 * @brief Database record extension
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\RsExt;

use Dotclear\Database\RecordExtend;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

abstract class RsExtend extends RecordExtend
{
    public function __construct()
    {
        // more to come
    }
}
