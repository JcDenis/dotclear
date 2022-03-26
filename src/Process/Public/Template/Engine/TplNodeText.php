<?php
/**
 * @class Dotclear\Process\Public\Template\Engine\TplNodeText
 * @brief Text node, for any non-tpl content
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
use Dotclear\Process\Public\Template\Engine\TplNode;

class TplNodeText extends TplNode
{
    public function __construct(protected string $content)
    {
        parent::__construct();
    }

    public function compile(Template $tpl): string
    {
        return $this->content;
    }

    public function getTag(): string
    {
        return 'TEXT';
    }
}
