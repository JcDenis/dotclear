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

trait TraitDefineTheme
{
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
    }
}
