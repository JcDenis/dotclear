<?php
/**
 * @class Dotclear\Process\Public\Template\Engine\TplNodeValueParent
 * @brief Value node, for all {{tpl:Tag}}
 *
 * @package Clearbricks
 * @subpackage Template
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public\Template\Engine;

use Dotclear\Process\Public\Template\Engine\Template;
use Dotclear\Process\Public\Template\Engine\TplNodeValue;
use Dotclear\Process\Public\Template\Engine\TplNodeBlockDefinition;

class TplNodeValueParent extends TplNodeValue
{
    public function compile(Template $tpl): string
    {
        # Simply ask currently being displayed to display itself!
        return TplNodeBlockDefinition::renderParent($tpl);
    }
}
