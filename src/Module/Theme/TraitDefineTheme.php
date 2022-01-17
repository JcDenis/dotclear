<?php
/**
 * @class Dotclear\Module\Theme\TraitDefineTheme
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
use Dotclear\Module\Plugin\TraitDefinePlugin;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefineTheme
{
    use TraitDefinePlugin;

    /**
     * Set module template set
     *
     * @param   string  The module template set
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setTemplateset(?string $templateset): AbstractDefine
    {
        $this->properties['templateset'] = $templateset;

        return $this;
    }

    /**
     * Set module parent
     *
     * @param   string  The module parent
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setParent(?string $parent): AbstractDefine
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Set module screenshot URL
     *
     * @param   string  The module screenshot URL
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setScreenshot(string $screenshot): AbstractDefine
    {
        $this->screenshot = $screenshot;

        return $this;
    }

    public function templateset(): ?string
    {
        return $this->properties['templateset'] ?? null;
    }

    public function parent(): ?string
    {
        return $this->properties['parent'] ?? null;
    }

    public function screenshot(): string
    {
        return $this->properties['screenshot'] ?: '';
    }

    protected function checkDefineTheme(): void
    {
        $this->properties = array_merge(
            [
                'templateset' => null,
                'parent'      => null,
                'screenshot'  => '',
            ],
            $this->properties,
            [
                'permissions' => 'admin'
            ]
        );

        $this->checkDefinePlugin();
    }
}
