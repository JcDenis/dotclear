<?php
/**
 * @class Dotclear\Public\Template\TraitTemplate
 * @brief Dotclear trait public template
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Public\Template;

use Dotclear\Public\Template\Template;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitTemplate
{
    /** @var    Template   Template instance */
    private $template;

    /**
     * Get instance
     *
     * @param   string  $cache_dir  The cache directory
     * @param   string  $self_name  The self name
     *
     * @return  Template            Template instance
     */
    public function template(string $cache_dir = null, string $self_name = null): Template
    {
        if (null !== $cache_dir && null !== $self_name) {
            $this->template = new Template($cache_dir, $self_name);
        }

        return $this->template;
    }
}
