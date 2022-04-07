<?php
/**
 * @class Dotclear\Module\Iconset\DefineIconset
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Iconset;

use Dotclear\Module\AbstractDefine;

class DefineIconset extends AbstractDefine
{
    protected $type = 'Iconset';

    # Disable permissions on Iconset
    public function permissions(): ?string
    {
        return null;
    }

    # Disable priority on Iconset
    public function priority(): int
    {
        return 1000;
    }
}
