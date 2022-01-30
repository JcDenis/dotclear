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

use Dotclear\Module\AbstractDefine;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

trait TraitDefine
{
    /**
     * Set module name
     *
     * It can use l10n feature.
     *
     * @param   string  The module name
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setName(string $name): AbstractDefine
    {
        $this->properties['name'] = $name;

        return $this;
    }

    /**
     * Set module descritpion
     *
     * It can use l10n feature.
     * Use only few words to describe module.
     *
     * @param   string  The module description
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setDescription(string $description): AbstractDefine
    {
        $this->properties['description'] = $description;

        return $this;
    }

    /**
     * Set module author
     *
     * @param  string  The module author
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setAuthor(string $author): AbstractDefine
    {
        $this->properties['author'] = $author;

        return $this;
    }

    /**
     * Set module version
     *
     * @param   string  The module version
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setVersion(string $version): AbstractDefine
    {
        $this->properties['version'] = $version;

        return $this;
    }

    /**
     * Set module type
     *
     * For now can be 'Plugin' or 'Theme'.
     *
     * @param   string  The module type
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setType(string $type): AbstractDefine
    {
        $this->properties['type'] = $type;

        return $this;
    }

    /**
     * Set module requirements
     *
     * Array of [module, version]. For exemple:
     * if module requires Dotclear 3.0 and AboutConfig 2.0.1
     * method must returns [['core', '3.0'], ['AboutConfig', '2.0.1']]
     *
     * @param   array   The module requirements
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setRequires(array $requires): AbstractDefine
    {
        $this->properties['requires'] = $requires;

        return $this;
    }

    /**
     * Set module support URL
     *
     * @param   string  The module support URL
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setSupport(string $support): AbstractDefine
    {
        $this->properties['support'] = $support;

        return $this;
    }

    /**
     * Set module details URL
     *
     * @param   string  The module details URL
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setDetails(string $details): AbstractDefine
    {
        $this->properties['details'] = $details;

        return $this;
    }

    /**
     * Set module optionnal definition
     *
     * It is not recommanded to use extra definition.
     * Value type is not tested.
     *
     * @param   string  $key    The option key
     * @param   mixed   $value  The option value
     *
     * @return  AbstractDefine  Self instance
     */
    protected function setOptions(string $key, mixed $value): AbstractDefine
    {
        $this->properties['options'][$key] = $value;

        return $this;
    }

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
        }

        if (empty($this->properties['description'])) {
            $this->error(sprintf(
                __('Module "%s" has no description.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
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
