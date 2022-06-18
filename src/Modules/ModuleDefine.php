<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules;

// Dotclear\Modules\ModuleDefine
use Dotclear\Helper\Configuration;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;

/**
 * Module define default structure.
 *
 * This class provides all necessary informations about Module.
 * Module definition must be on a define.xml file
 * into the module root path.
 *
 * @ingroup  Module
 */
class ModuleDefine extends Configuration
{
    /**
     * @var array<int,string> $dep_parents
     *                        Module parents dependencies
     */
    private $dep_parents = [];

    /**
     * @var array<int,string> $dep_children
     *                        Module children dependencies
     */
    private $dep_children = [];

    /**
     * @var array<int,string> $dep_missing
     *                        Module missing dependencies
     */
    private $dep_missing = [];

    // / @name Configuration methods
    // @{
    /**
     * Constructor.
     *
     * Requires a file that content calls to "set" methods.
     *
     * @param string                     $type Module type
     * @param string                     $id   Module id
     * @param array<string,mixed>|string $args Module Define file path or properties array
     */
    public function __construct(protected string $type, protected string $id, string|array $args)
    {
        if (is_string($args) && !empty($args)) {
            $args .= '/module.conf.php';
        }

        parent::__construct($this->getDefaultConfig(), $args);

        if (is_string($args)) {
            $this->set('root', dirname($args));
        } else {
            if (!empty($args['root']) && is_string($args['root']) && is_dir($args['root'])) {
                $this->set('root', $args['root']);
            }
        }

        $this->set('writable', !empty($this->get('root')) && is_writable($this->get('root')));

        $this->checkProperties();
    }

    protected function nullString(string $str): ?string
    {
        return empty($this->get($str)) || 'NULL' == $this->get($str) ? null : $this->get($str);
    }

    /**
     * Check properties.
     */
    protected function checkProperties(): void
    {
        if (empty($this->get('name'))) {
            $this->error()->add(sprintf(
                __('Module "%s" has no name.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->get('description'))) {
            $this->error()->add(sprintf(
                __('Module "%s" has no description.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->get('author'))) {
            $this->error()->add(sprintf(
                __('Module "%s" has no author.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->get('version'))) {
            $this->error()->add(sprintf(
                __('Module "%s" has no version.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        $this->set('id', $this->id);
        $this->set('type', ('' == $this->get('type') ? $this->type : ucfirst(strtolower($this->get('type')))));

        if ($this->get('type') != $this->type) {
            $this->error()->add(sprintf(
                __('Module "%s" has type "%s" that mismatch required module type "%s".'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>',
                '<em>' . Html::escapeHTML($this->get('type')) . '</em>',
                '<em>' . Html::escapeHTML($this->type) . '</em>'
            ));
        }

        $this->set('sname', preg_replace('/[^A-Za-z0-9\@\#+_-]/', '', strtolower($this->get('name'))));

        $r = [];
        foreach ($this->get('requires') as $k => $v) {
            if (is_string($v)) {
                $r[$k] = $v;
            }
        }
        $this->set('requires', $r);

        $r = [];
        foreach ($this->get('settings') as $k => $v) {
            if (is_string($v)) {
                $r[$k] = $v;
            }
        }
        $this->set('settings', $r);

        $r = $this->get('repository');
        if (!empty($r)) {
            $this->set('repositiory', (substr($r, -12, 12) == '/dcstore.xml' ? $r : Http::concatURL($r, 'dcstore.xml')));
        }

        $r = [];
        foreach ($this->get('tags') as $k => $v) {
            if (is_string($v)) {
                $r[$k] = $v;
            }
        }
        $this->set('tags', $r);
    }
    // @}

    // / @name Definitions methods
    // @{
    /**
     * Get module properties.
     *
     * @param bool $reload Reload properties
     *
     * @return array<string,mixed> The module properties
     */
    public function properties(bool $reload = false): array
    {
        if ($reload) {
            $this->checkProperties();
        }

        return $this->dump();
    }

    /**
     * Disable/Enable module.
     *
     * @param bool $disable True to disable module
     *
     * @return static Module define instance
     */
    final public function disableModule(bool $disable = true): static
    {
        $this->set('enabled', !$disable);

        return $this;
    }

    /**
     * Get / Set dependency parents.
     *
     * @param null|string $arg Parent id or null to get parents
     *
     * @return array<int,string> List of parents
     */
    final public function depParents(?string $arg = null): array
    {
        if (null !== $arg) {
            $this->dep_parents[] = $arg;
        }

        return $this->dep_parents;
    }

    /**
     * Get / Set dependency children.
     *
     * @param null|string $arg Child id or null to get children
     *
     * @return array<int,string> List of children
     */
    final public function depChildren(?string $arg = null): array
    {
        if (null !== $arg) {
            $this->dep_children[] = $arg;
        }

        return $this->dep_children;
    }

    /**
     * Get / Set missing dependency.
     *
     * @param null|array<int,string> $arg List of missing dependencies
     *
     * @return array<int,string> List of missing modules
     */
    final public function depMissing(?array $arg = null): array
    {
        if (null !== $arg) {
            $this->dep_missing = $arg;
        }

        return $this->dep_missing;
    }

    /**
     * Get module id.
     *
     * @return string Module id
     */
    final public function id(): string
    {
        return $this->get('id');
    }

    /**
     * Get module name or id.
     *
     * @return string Module name
     */
    final public function nid(): string
    {
        return $this->get('name');
    }

    /**
     * Get module root path.
     *
     * @return string Module root path
     */
    final public function root(): string
    {
        return $this->get('root');
    }

    /**
     * Check if module path is writable.
     *
     * @return bool True if writable path
     */
    final public function writable(): bool
    {
        return $this->get('writable');
    }

    /**
     * Check if module is enabled.
     *
     * @return bool True if enabled
     */
    final public function enabled(): bool
    {
        return $this->get('enabled');
    }

    /**
     * Check user permissions on module.
     *
     * Returns a comma separeated list of permissions
     * or null for super admin
     *
     * @return null|string Permissions
     */
    public function permission(): ?string
    {
        // This forced permissions on Theme
        if ('Theme' == $this->get('type')) {
            return 'usage,contentadmin';
        }

        return $this->nullString('permissions');
    }

    /**
     * Get module name.
     *
     * @return string Module name
     */
    public function name(): string
    {
        return $this->get('name');
    }

    /**
     * Get module sanitized name.
     *
     * @return string Module sanitized name
     */
    public function sname(): string
    {
        return $this->get('sname');
    }

    /**
     * Get module description.
     *
     * @param bool $trans Do not translate property
     *
     * @return string Module description
     */
    public function description(bool $trans = true): string
    {
        return $this->get('description');
    }

    /**
     * Get module author.
     *
     * @return string Module author
     */
    public function author(): string
    {
        return $this->get('author');
    }

    /**
     * Get module version.
     *
     * @return string Module version
     */
    public function version(): string
    {
        return $this->get('version');
    }

    /**
     * Get module current version (used from install/update process).
     *
     * @return string Module current version
     */
    public function currentVersion(): string
    {
        return $this->get('current_version');
    }

    /**
     * Get module type.
     *
     * @param bool $to_lower True for lowercase
     *
     * @return string Module type
     */
    public function type(bool $to_lower = false): string
    {
        return $to_lower ? strtolower($this->get('type')) : $this->get('type');
    }

    /**
     * Get module priority.
     *
     * @return int Module priority
     */
    public function priority(): int
    {
        return $this->get('priority');
    }

    /**
     * Get module requriements.
     *
     * @return array<string,string> Module requirements
     */
    public function requires(): array
    {
        return $this->get('requires');
    }

    /**
     * Get module support URL.
     *
     * @return string Module support URL
     */
    public function support(): string
    {
        return $this->get('support');
    }

    /**
     * Get module details URL.
     *
     * @return string Module details URL
     */
    public function details(): string
    {
        return $this->get('details');
    }

    /**
     * Get module option(s).
     *
     * @param null|string $key The Option or null for all
     *
     * @return mixed Module option(s)
     */
    public function options(?string $key = null): mixed
    {
        if (null === $key) {
            return $this->get('options');
        }
        $o = $this->get('options');

        return $o[$key] ?? null;
    }

    /**
     * Get module standalone configuration.
     *
     * @return bool True for standalone config
     */
    public function standaloneConfig(): bool
    {
        return $this->get('standalone_config');
    }

    /**
     * Get module settings.
     *
     * @return array<string,string> Module defined settings
     */
    public function settings(): array
    {
        return $this->get('settings');
    }

    /**
     * Get module repository URL.
     *
     * @return string Module repository URL
     */
    public function repository(): string
    {
        return $this->get('repository');
    }

    /**
     * Get module templateset (theme).
     *
     * @return string Module templateset
     */
    public function templateset(): ?string
    {
        return $this->nullString('templateset');
    }

    /**
     * Get module parent (theme).
     *
     * @return string Module parent
     */
    public function parent(): ?string
    {
        return $this->nullString('parent');
    }

    /**
     * Get module screenshot URL (theme).
     *
     * @return string Module screenshot URL
     */
    public function screenshot(): string
    {
        return $this->get('screenshot');
    }

    /**
     * Get module file path (store).
     *
     * @return string Module file path
     */
    public function file(): string
    {
        return $this->get('file');
    }

    /**
     * Get module section (store).
     *
     * @return string Module section
     */
    public function section(): string
    {
        return $this->get('section');
    }

    /**
     * Get module tags (store).
     *
     * @return array<string,string> Module tags
     */
    public function tags(): array
    {
        return $this->get('tags');
    }

    /**
     * Get module score (store).
     *
     * @return int Module score
     */
    public function score(): int
    {
        return $this->get('score');
    }
    // @}

    /**
     * Default module configuration.
     *
     * This configuration must be completed by
     * the module.conf.php file.
     *
     * @return array<string,array> Initial configuation
     */
    private function getDefaultConfig()
    {
        return [
            'id'                => [null, ''],
            'root'              => [false, ''],
            'writable'          => [false, false],
            'enabled'           => [false, true],
            'permissions'       => [null, 'NULL'],
            'name'              => [true, ''],
            'description'       => [true, ''],
            'author'            => [true, ''],
            'version'           => [true, ''],
            'current_version'   => [null, ''],
            'type'              => [null, ''],
            'priority'          => [null, 1000],
            'requires'          => [null, []],
            'details'           => [null, ''],
            'support'           => [null, ''],
            'options'           => [null, []],
            'standalone_config' => [null, false],
            'settings'          => [null, []],
            'sname'             => [false, ''],
            'repository'        => [null, ''],
            'templateset'       => [null, 'NULL'],
            'parent'            => [null, 'NULL'],
            'screenshot'        => [null, ''],
            'section'           => [null, ''],
            'tags'              => [null, []],
            'score'             => [null, 0],
            'file'              => [null, ''],
        ];
    }
}
