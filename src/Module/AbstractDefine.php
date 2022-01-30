<?php
/**
 * @class Dotclear\Module\AbstractDefine
 * @brief Dotclear Module define default structure
 *
 * This class provides all necessary informations about Module.
 * It includes file on load. This file must be a valid php file
 * and content calls to Define methods.
 * ie: $this->setName('MyPluginName') ...
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

use Dotclear\Exception\ModuleException;

use Dotclear\Html\TraitError;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

abstract class AbstractDefine
{
    use TraitError;

    /** @var    array   Module cleaned properties */
    protected $properties = [
        'id'                => '',
        'root'              => '',
        'writable'          => false,
        'enabled'           => true,
    ];

    /** @var    array   Module parents dependencies */
    private $dep_parents = [];

    /** @var    array   Module children dependencies */
    private $dep_children = [];

    /** @var    array   Module missing dependencies */
    private $dep_missing = [];

    /** @var    string  Required module type */
    protected $type = '';

    /**
     * Constructor
     *
     * Requires a file that content calls to "set" methods.
     *
     * @param   string|array  $file           Module Define file path
     * @param   string  $id             Module id
     * @param   array   $types_allowed  Allowed module types
     */
    public function __construct(string $id, string|array $args)
    {
        if (is_array($args)) {
            $this->newFromArray($id, $args);
        } else {
            $this->newFromFile($id, $args);
        }

        $this->checkModule();
    }

    private function newFromArray(string $id, array $properties): void
    {
        $this->properties = $properties;
        $this->properties['id']       = $id;
        $this->properties['root']     = empty($properties['root']) ? '' : dirname($file);
        $this->properties['writable'] = !empty($this->properties['root']) && is_writable($this->properties['root']);
    }

    private function newFromFile(string $id, string $file): void
    {
        $this->properties['id']       = $id;
        $this->properties['root']     = dirname($file);
        $this->properties['writable'] = !empty($this->properties['root']) && is_writable($this->properties['root']);

        if (!file_exists($file)) {
            throw new ModuleException(sprintf(
                __('Failed to open define file "%s" for module "%s".'),
                '<strong>' . Html::escapeHTML($file) . '</strong>',
                '<strong>' . Html::escapeHTML($id) . '</strong>'
            ));
        }

        require $file;
    }

    final public function disableModule(bool $disable = true): AbstractDefine
    {
        $this->properties['enabled'] = !$disable;

        return $this;
    }

    final public function depParents(?string $arg = null): array
    {
        if ($arg !== null) {
            $this->dep_parents[] = $arg;
        }

        return $this->dep_parents;
    }

    final public function depChildren(?string $arg = null): array
    {
        if ($arg !== null) {
            $this->dep_children[] = $arg;
        }

        return $this->dep_children;
    }

    final public function depMissing(?array $arg = null): array
    {
        if ($arg !== null) {
            $this->dep_missing = $arg;
        }

        return $this->dep_missing;
    }

    final public function id(): string
    {
        return $this->properties['id'];
    }

    final public function nid(): string
    {
        return $this->properties['name'] ?: $this->properties['id'];
    }

    final public function root(): string
    {
        return $this->properties['root'];
    }

    final public function writable(): bool
    {
        return $this->properties['writable'];
    }

    final public function enabled(): bool
    {
        return $this->properties['enabled'];
    }

    /**
     * Get module properties
     *
     * @param   bool    Reload properties
     *
     * @return  array   The module properties
     */
    public function properties(bool $reload = false): array
    {
        if ($reload) {
            $this->checkModule();
        }

        return $this->properties;
    }

    public function __call($k, $v)
    {
        throw new ModuleException(sprintf(__('Unknow module property "%s"'), $k));
    }

    abstract protected function checkModule(): void;
}
