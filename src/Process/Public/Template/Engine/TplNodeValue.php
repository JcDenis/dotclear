<?php
/**
 * @class Dotclear\Process\Public\Template\Engine\TplNodeValue
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
use Dotclear\Process\Public\Template\Engine\TplNode;

class TplNodeValue extends TplNode
{
    protected $content = '';

    public function __construct(protected string $tag, protected array $attr, protected string $str_attr)
    {
        parent::__construct();
    }

    public function compile(Template $tpl): string
    {
        return $tpl->compileValueNode($this->tag, $this->attr, $this->str_attr);
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}
