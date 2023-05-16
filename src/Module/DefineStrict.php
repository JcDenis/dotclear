<?php
/**
 * @brief Plugin My module class.
 * 
 * A plugin My class must extend this class.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.27
 */
declare(strict_types=1);

namespace Dotclear\Module;

/**
 * Define strict type hinting.
 * 
 * "XxxStrict" class are immutable and returns single type by var.
 */
final class DefineStrict
{
    public readonly string $id;

    public readonly int $state;
    public readonly string $root;
    public readonly string $namespace;
    public readonly bool $root_writable;
    public readonly bool $distributed;

    public readonly string $name;
    public readonly string $desc;
    public readonly string $author;
    public readonly string $version;
    public readonly string $type;

    public readonly string $permissions;
    public readonly int $priority;
    public readonly bool $standalone_config;
    /** @var    array<int,array{int,string}> */
    public readonly array $requires;
    /** @var    array<string,string> */
    public readonly array $settings;

    public readonly string $label;
    public readonly string $support;
    public readonly string $details;
    public readonly string $repository;

    public readonly string $parent;
    public readonly string $tplset;

    public readonly string $file;
    public readonly string $current_version;

    public readonly string $section;
    /** @var    array<int,string> */
    public readonly array $tags;
    public readonly string $sshot;
    public readonly int $score;
    public readonly string $dc_min;

    public readonly string $sid;
    public readonly string $sname;

    public readonly bool $defined;
    public readonly bool $enabled;
    /** @var    array<int,string> */
    public readonly array $implies;
    /** @var    array<string,string> */
    public readonly array $missing;
    /** @var    array<int,string> */
    public readonly array $using;

    /**
     * Constructor sets properties.
     * 
     * @param   Define  The module define
     */
    public function __construct(Define $define) {
        $this->id            = $define->id;

        // set by dc
        $this->state         = is_numeric($define->get('state')) ? (int) $define->get('state') : Define::STATE_INIT_DISABLED;
        $this->root          = is_string($define->get('root')) ? $define->get('root') : '';
        $this->namespace     = is_string($define->get('namespace')) ? $define->get('namespace') : '';
        $this->root_writable = !empty($define->get('root_writable'));
        $this->distributed   = !empty($define->get('distributed'));

        // required
        $this->name    = is_string($define->get('name')) ? $define->get('name') : $this->id;
        $this->desc    = is_string($define->get('desc')) ? $define->get('desc') : '';
        $this->author  = is_string($define->get('author')) ? $define->get('author') : 'unknown';
        $this->version = is_string($define->get('version')) ? $define->get('version') : '0';
        $this->type    = is_string($define->get('type')) ? $define->get('type') : Define::DEFAULT_TYPE;

        // optionnal
        $this->permissions       = is_string($define->get('permissions')) ? $define->get('permissions') : '';
        $this->priority          = is_numeric($define->get('priority')) ? (int) $define->get('priority') : Define::DEFAULT_PRIORITY;
        $this->standalone_config = !empty($define->get('standalone_config'));
        $requires                = is_array($define->get('requires')) ? $define->get('requires') : [];
        foreach ($requires as $k => $dep) {
            if (!is_array($dep)) {
                $dep = [$dep];
            }
            if (is_string($dep[0]) && (!isset($dep[1]) || is_string($dep[1]))) {
                $requires[$k] = $dep;
            } else {
                unset($requires[$k]);
            }
        }
        $this->requires = array_values($requires);
        $settings       = is_array($define->get('settings')) ? $define->get('settings') : [];
        foreach ($settings as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $settings[$k] = $v;
            } else {
                unset($settings[$k]);
            }
        }
        $this->settings = $settings;

        // optionnal++
        $this->label      = is_string($define->get('label')) ? $define->get('label') : $this->name;
        $this->support    = is_string($define->get('support')) ? $define->get('support') : '';
        $this->details    = is_string($define->get('details')) ? $define->get('details') : '';
        $this->repository = is_string($define->get('repository')) ? $define->get('repository') : '';

        // theme specifics
        $this->parent = is_string($define->get('parent')) ? $define->get('parent') : '';
        $this->tplset = is_string($define->get('tplset')) ? $define->get('tplset') : DC_DEFAULT_TPLSET;

        // store specifics
        $this->file            = is_string($define->get('file')) ? $define->get('file') : '';
        $this->current_version = is_string($define->get('current_version')) ? $define->get('current_version') : '0';

        // DA specifics
        $this->section = is_string($define->get('version')) ? $define->get('version') : '0';
        $this->tags    = is_string($define->get('tags')) ? explode(',', $define->get('tags')) : [];
        $this->sshot   = is_string($define->get('sshot')) ? $define->get('sshot') : '';
        $this->score   = is_numeric($define->get('score')) ? (int) $define->get('score') : 0;
        $this->dc_min  = is_string($define->get('dc_min')) ? $define->get('dc_min') : '2.0';

        // modules list specifics
        $this->sid   = is_string($define->get('sid')) ? $define->get('sid') : '';
        $this->sname = is_string($define->get('sname')) ? $define->get('sname') : '';

        // out of properties
        $this->defined = $this->name != Define::DEFAULT_NAME;
        $this->enabled = $this->state == Define::STATE_ENABLED;
        $this->implies = $define->getImplies();
        $this->missing = $define->getMissing();
        $this->using   = $define->getUsing();
    }

    /**
     * Catch overloaded properties.
     *
     * @return  null
     */
    public function __get(string $property): null
    {
        return null;
    }

    /**
     * Test if a property exists
     *
     * @param      string  $property  The property
     *
     * @return     bool     True on exists
     */
    public function __isset(string $property): bool
    {
        return property_exists($this, $property);
    }

    /**
     * Get an array copy of all properties.
     *
     * @return  array<string,mixed>     The properties
     */
    public function dump(): array
    {
        return get_object_vars($this);
    }
}
