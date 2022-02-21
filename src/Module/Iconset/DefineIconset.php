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
use Dotclear\Module\TraitDefine;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefineIconset extends AbstractDefine
{
    use TraitDefine;

    protected $type = 'Iconset';

    public function permissions(): ?string
    {
        return null;
    }

    public function priority(): int
    {
        return 1000;
    }

    protected function checkProperties(): void
    {
        $this->checkDefine();
    }
}
