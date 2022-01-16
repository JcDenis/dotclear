<?php
/**
 * @class Dotclear\Module\Theme\DefineTheme
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme;

use Dotclear\Module\AbstractDefine;
use Dotclear\Module\Theme\TraitDefineTheme;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class DefineTheme extends AbstractDefine
{
    use TraitDefineTheme;

    protected $type = 'Theme';

    protected function checkModule(): void
    {
        $this->checkDefineTheme();
    }
}
