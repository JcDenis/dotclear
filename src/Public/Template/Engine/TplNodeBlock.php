<?php
/**
 * @class Dotclear\Public\Template\Engine\TplNodeBlock
 * @brief Block node, for all <tpl:Tag>...</tpl:Tag>
 *
 * @package Clearbricks
 * @subpackage Template
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Public\Template\Engine;

use Dotclear\Public\Template\Engine\Template;
use Dotclear\Public\Template\Engine\TplNode;

class TplNodeBlock extends TplNode
{
    protected $attr;
    protected $tag;
    protected $closed;
    protected $content;

    public function __construct(string $tag, array $attr)
    {
        parent::__construct();
        $this->content = '';
        $this->tag     = $tag;
        $this->attr    = $attr;
        $this->closed  = false;
    }
    public function setClosing()
    {
        $this->closed = true;
    }
    public function isClosed()
    {
        return $this->closed;
    }
    public function compile(Template $tpl): string
    {
        if ($this->closed) {
            $content = parent::compile($tpl);

            return $tpl->compileBlockNode($this->tag, $this->attr, $content);
        }
        // if tag has not been closed, silently ignore its content...
        return '';
    }
    public function getTag()
    {
        return $this->tag;
    }
}
