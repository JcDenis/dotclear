<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module;

// Dotclear\Module\AbstractDefine
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\ErrorTrait;
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
abstract class AbstractDefine
{
    use ErrorTrait;

    /**
     * @var array<string,mixed> $properties
     *                          Module cleaned properties
     */
    protected $properties = [
        'id'                => '',
        'root'              => '',
        'writable'          => false,
        'enabled'           => true,
        'permissions'       => null,
        'name'              => '',
        'description'       => '',
        'author'            => '',
        'version'           => '',
        'current_version'   => '',
        'type'              => '',
        'priority'          => 1000,
        'requires'          => [],
        'details'           => '',
        'support'           => '',
        'options'           => [],
        'standalone_config' => false,
        'settings'          => [],
        'repository'        => '',
        'templateset'       => null,
        'parent'            => null,
        'screenshot'        => '',
        'section'           => '',
        'tags'              => [],
        'score'             => 0,
    ];

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

    /**
     * @var string $type
     *             Required module type
     */
    protected $type = '';

    /**
     * Constructor.
     *
     * Requires a file that content calls to "set" methods.
     *
     * @param string                      $id   Module id
     * @param array<string, mixed>|string $args Module Define file path or properties array
     */
    public function __construct(string $id, string|array $args)
    {
        if (is_array($args)) {
            $this->newFromArray($id, $args);
        } else {
            $this->newFromFile($id, $args);
        }

        $this->checkProperties();
    }

    /**
     * Load define from an array.
     *
     * @param string               $id         Module id
     * @param array<string, mixed> $properties Module properties
     */
    private function newFromArray(string $id, array $properties): void
    {
        $this->properties       = array_merge($this->properties, $properties);
        $this->properties['id'] = $id;
        if (empty($properties['root']) || !is_string($this->properties['root']) || !is_dir($properties['root'])) {
            $this->properties['root'] = '';
        }
        $this->properties['writable'] = !empty($this->properties['root']) && is_writable($this->properties['root']);
    }

    /**
     * Load define from a file.
     *
     * @param string $id   Module id
     * @param string $file File path
     */
    private function newFromFile(string $id, string $file): void
    {
        $e_file = '<strong>' . Html::escapeHTML($file) . '</strong>';
        $e_id   = '<strong>' . Html::escapeHTML($id) . '</strong>';

        if (!file_exists($file)) {
            throw new ModuleException(sprintf(
                __('Failed to open define file "%s" for module "%s".'),
                $e_file,
                $e_id
            ));
        }

        $contents = file_get_contents($file);
        if (!$contents) {
            throw new ModuleException(sprintf(
                __('Failed to get contents of define file "%s" for module "%s".'),
                $e_file,
                $e_id
            ));
        }

        $xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
        if (!$xml) {
            throw new ModuleException(sprintf(
                __('Failed to load xml content of define file "%s" for module "%s".'),
                $e_file,
                $e_id
            ));
        }

        $array = json_decode(json_encode($xml), true);
        if (!is_array($array)) {
            throw new ModuleException(sprintf(
                __('Failed to parse xml contents of define file "%s" for module "%s".'),
                $e_file,
                $e_id
            ));
        }

        $this->properties = array_merge(
            $this->properties,
            $array,
            [
                'id'       => $id,
                'root'     => dirname($file),
                'writable' => is_writable(dirname($file)),
            ]
        );
    }

    /**
     * Get module properties.
     *
     * @param bool $reload Reload properties
     *
     * @return array The module properties
     */
    public function properties(bool $reload = false): array
    {
        if ($reload) {
            $this->checkProperties();
        }

        return $this->properties;
    }

    /**
     * Check properties.
     */
    protected function checkProperties(): void
    {
        if ('NULL' == $this->properties['permissions']) {
            $this->properties['permissions'] = null;
        }

        if (empty($this->properties['name'])) {
            $this->error()->add(sprintf(
                __('Module "%s" has no name.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->properties['description'])) {
            $this->error()->add(sprintf(
                __('Module "%s" has no description.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->properties['author'])) {
            $this->error()->add(sprintf(
                __('Module "%s" has no author.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        if (empty($this->properties['version'])) {
            $this->error()->add(sprintf(
                __('Module "%s" has no version.'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>'
            ));
        }

        $this->properties['type'] = ucfirst(strtolower($this->properties['type']));

        if ($this->properties['type'] != $this->type) {
            $this->error()->add(sprintf(
                __('Module "%s" has type "%s" that mismatch required module type "%s".'),
                '<strong>' . Html::escapeHTML($this->nid()) . '</strong>',
                '<em>' . Html::escapeHTML($this->properties['type']) . '</em>',
                '<em>' . Html::escapeHTML($this->type) . '</em>'
            ));
        }

        $this->properties['priority'] = (int) $this->properties['priority'];
        $this->properties['sname']    = preg_replace('/[^A-Za-z0-9\@\#+_-]/', '', strtolower($this->properties['name']));

        foreach ($this->properties['requires'] as $k => $v) {
            if (!is_array($v)) {
                $this->properties['requires'][$k] = [$v];
            }
        }

        $this->properties['standalone_config'] = (bool) $this->properties['standalone_config'];

        $this->properties['repository'] = trim($this->properties['repository']);
        if (!empty($this->properties['repository'])) {
            $this->properties['repositiory'] = substr($this->properties['repository'], -12, 12) == '/dcstore.xml' ?
                $this->properties['repository'] :
                Http::concatURL($this->properties['repository'], 'dcstore.xml');
        }

        foreach ($this->properties['tags'] as $k => $v) {
            if (!is_string($v)) {
                unset($this->properties['tags'][$k]);
            }
        }
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
        $this->properties['enabled'] = !$disable;

        return $this;
    }

    /**
     * Get / Set dependency parents.
     *
     * @param null|string $arg Parent id or null to get parents
     *
     * @return array<int, string> List of parents
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
     * @return array<int, string> List of children
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
     * @param null|array<int, string> $arg List of missing dependencies
     *
     * @return array<int, string> List of missing modules
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
        return $this->properties['id'];
    }

    /**
     * Get module name or id.
     *
     * @return string Module name
     */
    final public function nid(): string
    {
        return $this->properties['name'] ?: $this->properties['id'];
    }

    /**
     * Get module root path.
     *
     * @return string Module root path
     */
    final public function root(): string
    {
        return $this->properties['root'];
    }

    /**
     * Check if module path is writable.
     *
     * @return bool True if writable path
     */
    final public function writable(): bool
    {
        return $this->properties['writable'];
    }

    /**
     * Check if module is enabled.
     *
     * @return bool True if enabled
     */
    final public function enabled(): bool
    {
        return $this->properties['enabled'];
    }

    /**
     * Check user permissions on module.
     *
     * Returns a comma separeated list of permissions
     * or null for super admin
     *
     * @return null|string Permissions
     */
    public function permissions(): ?string
    {
        return $this->properties['permissions'] ?? null;
    }

    /**
     * Get module name.
     *
     * @param bool $trans Translate property
     *
     * @return string Module name
     */
    public function name(bool $trans = true): string
    {
        $v = $this->properties['name'] ?: '';

        return $trans ? __($v) : $v;
    }

    /**
     * Get module sanitized name.
     *
     * @return string Module sanitized name
     */
    public function sname(): string
    {
        return $this->properties['sname'] ?: '';
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
        $v = $this->properties['description'] ?: '';

        return $trans ? __($v) : $v;
    }

    /**
     * Get module author.
     *
     * @return string Module author
     */
    public function author(): string
    {
        return $this->properties['author'] ?: '';
    }

    /**
     * Get module version.
     *
     * @return string Module version
     */
    public function version(): string
    {
        return $this->properties['version'] ?: '';
    }

    /**
     * Get module current version (used from install/update process).
     *
     * @return string Module current version
     */
    public function currentVersion(): string
    {
        return $this->properties['current_version'] ?: '';
    }

    /**
     * Get module type.
     *
     * @return string Module type
     */
    public function type(): string
    {
        return $this->properties['type'] ?: '';
    }

    /**
     * Get module priority.
     *
     * @return int Module priority
     */
    public function priority(): int
    {
        return $this->properties['priority'] ?: 1000;
    }

    /**
     * Get module requriements.
     *
     * @return array<string, array> Module requirements
     */
    public function requires(): array
    {
        return $this->properties['requires'] ?: [];
    }

    /**
     * Get module support URL.
     *
     * @return string Module support URL
     */
    public function support(): string
    {
        return $this->properties['support'] ?: '';
    }

    /**
     * Get module details URL.
     *
     * @return string Module details URL
     */
    public function details(): string
    {
        return $this->properties['details'] ?: '';
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
            return $this->properties['options'];
        }

        return $this->properties['options'][$key] ?? null;
    }

    /**
     * Get module standalone configuration.
     *
     * @return bool True for standalone config
     */
    public function standaloneConfig(): bool
    {
        return $this->properties['standalone_config'] ?: false;
    }

    /**
     * Get module settings.
     *
     * @return array Module defined settings
     */
    public function settings(): array
    {
        return $this->properties['settings'] ?: [];
    }

    /**
     * Get module repository URL.
     *
     * @return string Module repository URL
     */
    public function repository(): string
    {
        return $this->properties['repository'] ?: '';
    }

    /**
     * Get module templateset (theme).
     *
     * @return string Module templateset
     */
    public function templateset(): ?string
    {
        return $this->properties['templateset'] ?? null;
    }

    /**
     * Get module parent (theme).
     *
     * @return string Module parent
     */
    public function parent(): ?string
    {
        return $this->properties['parent'] ?? null;
    }

    /**
     * Get module screenshot URL (theme).
     *
     * @return string Module screenshot URL
     */
    public function screenshot(): string
    {
        return $this->properties['screenshot'] ?: '';
    }

    /**
     * Get module file path (store).
     *
     * @return string Module file path
     */
    public function file(): string
    {
        return $this->properties['file'] ?: '';
    }

    /**
     * Get module section (store).
     *
     * @return string Module section
     */
    public function section(): string
    {
        return $this->properties['section'] ?: '';
    }

    /**
     * Get module tags (store).
     *
     * @return array Module tags
     */
    public function tags(): array
    {
        return $this->properties['tags'] ?: [];
    }

    /**
     * Get module score (store).
     *
     * @return int Module score
     */
    public function score(): int
    {
        return $this->properties['score'] ?: 0;
    }
}
