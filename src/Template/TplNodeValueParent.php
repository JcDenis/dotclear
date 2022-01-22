<?php
/**
 * @class tplNodeValueParent
 * @brief Value node, for all {{tpl:Tag}}
 *
 * @package Clearbricks
 * @subpackage Template
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Template;

use Dotclear\Template\Template;
use Dotclear\Template\TplNodeValue;
use Dotclear\Template\TplNodeBlockDefinition;

class TplNodeValueParent extends TplNodeValue
{
    public function compile(Template $tpl): string
    {
        # Simply ask currently being displayed to display itself!
        return TplNodeBlockDefinition::renderParent($tpl);
    }
}
