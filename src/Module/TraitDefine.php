<?php
/**
 * @class Dotclear\Module\TraitDefine
 * @brief Dotclear Module define default specific structure
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefine
{
    public function name(): string
    {
        return $this->properties['name'] ?: '';
    }

    public function sname(): string
    {
        return $this->properties['sname'] ?: '';
    }

    public function description(): string
    {
        return $this->properties['description'] ?: '';
    }

    public function author(): string
    {
        return $this->properties['author'] ?: '';
    }

    public function version(): string
    {
        return $this->properties['version'] ?: '';
    }

    public function currentVersion(): string
    {
        return $this->properties['current_version'] ?: '';
    }

    public function type(): string
    {
        return $this->properties['type'] ?: '';
    }

    public function requires(): array
    {
        return $this->properties['requires'] ?: [];
    }

    public function support(): string
    {
        return $this->properties['support'] ?: '';
    }

    public function details(): string
    {
        return $this->properties['details'] ?: '';
    }

    public function options(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->properties['options'];
        }

        return $this->properties['options'][$key] ?? null;
    }

    protected function checkDefine(): void
    {
        $this->properties = array_merge(
            [
                'name'            => '',
                'description'     => '',
                'author'          => '',
                'version'         => '',
                'current_version' => '',
                'type'            => '',
                'requires'        => [],
                'details'         => '',
                'support'         => '',
                'options'         => [],
            ],
            $this->properties
        );

        if (empty($this->properties['name'])) {
            $this->error(sprintf(
                __('Module "%s" has no name.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        } else {
            $this->properties['name'] = __($this->properties['name']);
        }

        if (empty($this->properties['description'])) {
            $this->error(sprintf(
                __('Module "%s" has no description.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        } else {
            $this->properties['description'] = __($this->properties['description']);
        }

        if (empty($this->properties['author'])) {
            $this->error(sprintf(
                __('Module "%s" has no author.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->properties['version'])) {
            $this->error(sprintf(
                __('Module "%s" has no version.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        $this->properties['type'] = ucfirst(strtolower($this->properties['type']));

        if ($this->properties['type'] != $this->type) {
            $this->error(sprintf(
                __('Module "%s" has type "%s" that mismatch required module type "%s".'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>',
                '<em>' . Html::escapeHTML($this->properties['type']) . '</em>',
                '<em>' . Html::escapeHTML($this->type) . '</em>'
            ));
        }

        $this->properties['sname'] = preg_replace('/[^A-Za-z0-9\@\#+_-]/', '', strtolower($this->properties['name']));

        foreach($this->properties['requires'] as $k => $v) {
            if (!is_array($v)) {
                $this->properties['requires'][$k] = [$v];
            }
        }
    }
}
