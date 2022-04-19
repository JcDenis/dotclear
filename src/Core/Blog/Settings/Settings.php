<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Settings;

use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;

/**
 * Blog settings handling methods.
 *
 * \Dotclear\Core\Blog\Settings\Settings
 *
 * @ingroup  Core Setting
 */
class Settings
{
    /** @var string Setting table name */
    protected $table;

    /** @var array Associative namespaces array */
    protected $namespaces = [];

    /** @var string Current namespace */
    protected $ns;

    protected const NS_NAME_SCHEMA = '/^[a-zA-Z][a-zA-Z0-9]+$/';

    /**
     * Constructor.
     *
     * Retrieves blog settings and puts them in $namespaces
     * array. Local (blog) settings have a highest priority than global settings.
     *
     * @param null|string $blog_id The blog identifier
     */
    public function __construct(protected string|null $blog_id)
    {
        $this->table = dotclear()->prefix . 'setting';
        $this->loadSettings();
    }

    /**
     * Retrieves all namespaces.
     *
     * (and their settings) from database, with one query.
     */
    private function loadSettings(): void
    {
        try {
            $sql = new SelectStatement(__METHOD__);
            $rs  = $sql
                ->columns([
                    'blog_id',
                    'setting_id',
                    'setting_value',
                    'setting_type',
                    'setting_label',
                    'setting_ns',
                ])
                ->from($this->table)
                ->where('blog_id = ' . $sql->quote($this->blog_id))
                ->or('blog_id IS NULL')
                ->order(['setting_ns ASC', 'setting_id DESC'])
                ->select()
            ;
        } catch (\Exception) {
            trigger_error(__('Unable to retrieve namespaces:') . ' ' . dotclear()->con()->error(), E_USER_ERROR);
        }

        // Prevent empty tables (install phase, for instance)
        if ($rs->isEmpty()) {
            return;
        }

        do {
            $ns = trim($rs->f('setting_ns'));
            if (!$rs->isStart()) {
                // we have to go up 1 step, since namespaces construction performs
                // a fetch() at very first time
                $rs->movePrev();
            }
            $this->namespaces[$ns] = new Settingspace($this->blog_id, $ns, $rs);
        } while (!$rs->isStart());
    }

    /**
     * Create a new namespace.
     *
     * If the namespace already exists, return it without modification.
     *
     * @param string $ns Namespace name
     */
    public function addNamespace(string $ns): Settingspace
    {
        if (!$this->exists($ns)) {
            $this->namespaces[$ns] = new Settingspace($this->blog_id, $ns);
        }

        return $this->namespaces[$ns];
    }

    /**
     * Rename a namespace.
     *
     * @param string $oldNs The old ns
     * @param string $newNs The new ns
     *
     * @throws CoreException
     *
     * @return bool Return true if no error, else false
     */
    public function renNamespace(string $oldNs, string $newNs): bool
    {
        if (!$this->exists($oldNs) || $this->exists($newNs)) {
            return false;
        }

        if (!preg_match(self::NS_NAME_SCHEMA, $newNs)) {
            throw new CoreException(sprintf(__('Invalid setting namespace: %s'), $newNs));
        }

        // Rename the namespace in the namespace array
        $this->namespaces[$newNs] = $this->namespaces[$oldNs];
        unset($this->namespaces[$oldNs]);

        // Rename the namespace in the database
        $sql = new UpdateStatement(__METHOD__);
        $sql->from($this->table)
            ->set('setting_ns = ' . $sql->quote($newNs))
            ->where('setting_ns = ' . $sql->quote($oldNs))
            ->update()
        ;

        return true;
    }

    /**
     * Delete a whole namespace with all settings pertaining to it.
     *
     * @param string $ns Namespace name
     */
    public function delNamespace(string $ns): bool
    {
        if (!$this->exists($ns)) {
            return false;
        }

        // Remove the namespace from the namespace array
        unset($this->namespaces[$ns]);

        // Delete all settings from the namespace in the database
        $sql = new DeleteStatement(__METHOD__);
        $sql->from($this->table)
            ->where('setting_ns = ' . $sql->quote($ns))
            ->delete()
        ;

        return true;
    }

    /**
     * Returns full namespace with all settings pertaining to it.
     *
     * @param string $ns Namespace name
     */
    public function get(string $ns): Settingspace
    {
        return $this->addNamespace($ns);
    }

    /**
     * Check if a namespace exists.
     *
     * @param string $ns Namespace name
     */
    public function exists(string $ns): bool
    {
        return array_key_exists($ns, $this->namespaces);
    }

    /**
     * Dumps namespaces.
     */
    public function dump(): array
    {
        return $this->namespaces;
    }

    /**
     * Returns a list of settings matching given criteria, for any blog.
     *
     * <b>$params</b> is an array taking the following
     * optionnal parameters:
     * - ns : retrieve setting from given namespace
     * - id : retrieve only settings corresponding to the given id
     *
     * @param array $params The parameters
     *
     * @return Record the global settings
     */
    public function getGlobalSettings(array $params = []): Record
    {
        $sql = SelectStatement::init(__METHOD__)
            ->from($this->table)
            ->column('*')
            ->where('1 = 1')
            ->order('blog_id')
        ;

        if (!empty($params['ns'])) {
            $sql->and('setting_ns = ' . $sql->quote($params['ns']));
        }
        if (!empty($params['id'])) {
            $sql->and('setting_id = ' . $sql->quote($params['id']));
        }
        if (isset($params['blog_id'])) {
            $sql->and(
                empty($params['blog_id']) ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote($params['blog_id'])
            );
        }

        return $sql->select();
    }

    /**
     * Updates a setting from a given record.
     *
     * @param Record $rs The setting to update
     */
    public function updateSetting(Record $rs): void
    {
        $cur = dotclear()->con()->openCursor($this->table)
            ->setField('setting_id', $rs->f('setting_id'))
            ->setField('setting_value', $rs->f('setting_value'))
            ->setField('setting_type', $rs->f('setting_type'))
            ->setField('setting_label', $rs->f('setting_label'))
            ->setField('blog_id', $rs->f('blog_id'))
            ->setField('setting_ns', $rs->f('setting_ns'))
        ;

        $sql = new UpdateStatement(__METHOD__);
        $sql->where(
            null == $cur->getField('blog_id') ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote($cur->getField('blog_id'))
        )
            ->and('setting_id = ' . $sql->quote($cur->getField('setting_id')))
            ->and('setting_ns = ' . $sql->quote($cur->getField('setting_ns')))
            ->update($cur)
        ;
    }

    /**
     * Drops a setting from a given record.
     *
     * @param Record $rs The setting to drop
     *
     * @return int Number of deleted records (0 if setting does not exist)
     */
    public function dropSetting(Record $rs): int
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql->from($this->table)
            ->where(
                null == $rs->f('blog_id') ?
                'blog_id IS NULL' :
                'blog_id = ' . $sql->quote($rs->f('blog_id'))
            )
            ->and('setting_id = ' . $sql->quote($rs->f('setting_id')))
            ->and('setting_ns = ' . $sql->quote($rs->f('setting_ns')))
            ->delete()
        ;

        return dotclear()->con()->changes();
    }
}
