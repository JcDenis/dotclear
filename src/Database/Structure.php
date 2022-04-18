<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

use Dotclear\Exception\DatabaseException;

/**
 * Database structure manipulator.
 *
 * \Dotclear\Database\Structure
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Database Structure
 */
class Structure
{
    /** @var array Tables */
    protected $tables = [];

    /** @var array References */
    protected $references = [];

    /**
     * Constructor.
     *
     * @param AbstractConnection $con    Database connection
     * @param string             $prefix Database table prefix
     */
    public function __construct(protected AbstractConnection $con, protected string $prefix = '')
    {
    }

    /**
     * Get driver name.
     *
     * @return string The driver name
     */
    public function driver(): string
    {
        return $this->con->driver();
    }

    /**
     * Get table.
     *
     * @param string $name The table name
     *
     * @return Table The table
     */
    public function table(string $name): Table
    {
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = new Table($name);
        }

        return $this->tables[$name];
    }

    /**
     * Get database structure.
     */
    public function reverse(): void
    {
        $schema = AbstractSchema::init($this->con);

        // Get tables
        $tables = $schema->getTables();

        foreach ($tables as $t_name) {
            if ($this->prefix && !str_starts_with($t_name, $this->prefix)) {
                continue;
            }

            $t = $this->table($t_name);

            // Get columns
            $cols = $schema->getColumns($t_name);

            foreach ($cols as $c_name => $col) {
                $type = $schema->dbt2udt($col['type'], $col['len'], $col['default']);
                $t->field($c_name, $type, $col['len'], $col['null'], $col['default'], true);
            }

            // Get keys
            $keys = $schema->getKeys($t_name);

            foreach ($keys as $k) {
                $args = $k['cols'];
                array_unshift($args, $k['name']);

                if ($k['primary']) {
                    call_user_func_array([$t, 'primary'], $args);
                } elseif ($k['unique']) {
                    call_user_func_array([$t, 'unique'], $args);
                }
            }

            // Get indexes
            $idx = $schema->getIndexes($t_name);
            foreach ($idx as $i) {
                $args = [$i['name'], $i['type']];
                $args = array_merge($args, $i['cols']);

                call_user_func_array([$t, 'index'], $args);
            }

            // Get foreign keys
            $ref = $schema->getReferences($t_name);
            foreach ($ref as $r) {
                $t->reference($r['name'], $r['c_cols'], $r['p_table'], $r['p_cols'], $r['update'], $r['delete']);
            }
        }
    }

    /**
     * Synchronize this schema taken from database with $schema.
     *
     * @param Structure $s Structure to synchronize with
     *
     * @return int Number of changes
     */
    public function synchronize(Structure $s): int
    {
        $this->tables = [];
        $this->reverse();

        if (!($s instanceof self)) {
            throw new DatabaseException('Invalid database schema');
        }

        $tables = $s->getTables();

        $table_create     = [];
        $key_create       = [];
        $index_create     = [];
        $reference_create = [];

        $field_create     = [];
        $field_update     = [];
        $key_update       = [];
        $index_update     = [];
        $reference_update = [];

        $got_work = false;

        $schema = AbstractSchema::init($this->con);

        foreach ($tables as $tname => $t) {
            if (!$this->tableExists($tname)) {
                // Table does not exist, create table
                $table_create[$tname] = $t->getFields();

                // Add keys, indexes and references
                $keys       = $t->getKeys();
                $indexes    = $t->getIndexes();
                $references = $t->getReferences();

                foreach ($keys as $k => $v) {
                    $key_create[$tname][$this->prefix . $k] = $v;
                }
                foreach ($indexes as $k => $v) {
                    $index_create[$tname][$this->prefix . $k] = $v;
                }
                foreach ($references as $k => $v) {
                    $v['p_table']                                 = $this->prefix . $v['p_table'];
                    $reference_create[$tname][$this->prefix . $k] = $v;
                }

                $got_work = true;
            } else { // Table exists
                // Check new fields to create
                $fields    = $t->getFields();
                $db_fields = $this->tables[$tname]->getFields();
                foreach ($fields as $fname => $f) {
                    if (!$this->tables[$tname]->fieldExists($fname)) {
                        // Field doest not exist, create it
                        $field_create[$tname][$fname] = $f;
                        $got_work                     = true;
                    } elseif ($this->fieldsDiffer($db_fields[$fname], $f)) {
                        // Field exists and differs from db version
                        $field_update[$tname][$fname] = $f;
                        $got_work                     = true;
                    }
                }

                // Check keys to add or upgrade
                $keys    = $t->getKeys();
                $db_keys = $this->tables[$tname]->getKeys();

                foreach ($keys as $kname => $k) {
                    if ('primary' == $k['type'] && $this->con->syntax() == 'mysql') {
                        $kname = 'PRIMARY';
                    } else {
                        $kname = $this->prefix . $kname;
                    }

                    $db_kname = $this->tables[$tname]->keyExists($kname, $k['type'], $k['cols']);
                    if (!$db_kname) {
                        // Key does not exist, create it
                        $key_create[$tname][$kname] = $k;
                        $got_work                   = true;
                    } elseif ($this->keysDiffer($db_kname, $db_keys[$db_kname]['cols'], $kname, $k['cols'])) {
                        // Key exists and differs from db version
                        $key_update[$tname][$db_kname] = array_merge(['name' => $kname], $k);
                        $got_work                      = true;
                    }
                }

                // Check index to add or upgrade
                $idx    = $t->getIndexes();
                $db_idx = $this->tables[$tname]->getIndexes();

                foreach ($idx as $iname => $i) {
                    $iname    = $this->prefix . $iname;
                    $db_iname = $this->tables[$tname]->indexExists($iname, $i['type'], $i['cols']);

                    if (!$db_iname) {
                        // Index does not exist, create it
                        $index_create[$tname][$iname] = $i;
                        $got_work                     = true;
                    } elseif ($this->indexesDiffer($db_iname, $db_idx[$db_iname], $iname, $i)) {
                        // Index exists and differs from db version
                        $index_update[$tname][$db_iname] = array_merge(['name' => $iname], $i);
                        $got_work                        = true;
                    }
                }

                // Check references to add or upgrade
                $ref    = $t->getReferences();
                $db_ref = $this->tables[$tname]->getReferences();

                foreach ($ref as $rname => $r) {
                    $rname        = $this->prefix . $rname;
                    $r['p_table'] = $this->prefix . $r['p_table'];
                    $db_rname     = $this->tables[$tname]->referenceExists($rname, $r['c_cols'], $r['p_table'], $r['p_cols']);

                    if (!$db_rname) {
                        // Reference does not exist, create it
                        $reference_create[$tname][$rname] = $r;
                        $got_work                         = true;
                    } elseif ($this->referencesDiffer($db_rname, $db_ref[$db_rname], $rname, $r)) {
                        $reference_update[$tname][$db_rname] = array_merge(['name' => $rname], $r);
                        $got_work                            = true;
                    }
                }
            }
        }

        if (!$got_work) {
            return 0;
        }

        // Create tables
        foreach ($table_create as $table => $fields) {
            $schema->createTable($table, $fields);
        }

        // Create new fields
        foreach ($field_create as $tname => $fields) {
            foreach ($fields as $fname => $f) {
                $schema->createField($tname, $fname, $f['type'], $f['len'], $f['null'], $f['default']);
            }
        }

        // Update fields
        foreach ($field_update as $tname => $fields) {
            foreach ($fields as $fname => $f) {
                $schema->alterField($tname, $fname, $f['type'], $f['len'], $f['null'], $f['default']);
            }
        }

        // Create new keys
        foreach ($key_create as $tname => $keys) {
            foreach ($keys as $kname => $k) {
                if ('primary' == $k['type']) {
                    $schema->createPrimary($tname, $kname, $k['cols']);
                } elseif ('unique' == $k['type']) {
                    $schema->createUnique($tname, $kname, $k['cols']);
                }
            }
        }

        // Update keys
        foreach ($key_update as $tname => $keys) {
            foreach ($keys as $kname => $k) {
                if ('primary' == $k['type']) {
                    $schema->alterPrimary($tname, $kname, $k['name'], $k['cols']);
                } elseif ('unique' == $k['type']) {
                    $schema->alterUnique($tname, $kname, $k['name'], $k['cols']);
                }
            }
        }

        // Create indexes
        foreach ($index_create as $tname => $index) {
            foreach ($index as $iname => $i) {
                $schema->createIndex($tname, $iname, $i['type'], $i['cols']);
            }
        }

        // Update indexes
        foreach ($index_update as $tname => $index) {
            foreach ($index as $iname => $i) {
                $schema->alterIndex($tname, $iname, $i['name'], $i['type'], $i['cols']);
            }
        }

        // Create references
        foreach ($reference_create as $tname => $ref) {
            foreach ($ref as $rname => $r) {
                $schema->createReference($rname, $tname, $r['c_cols'], $r['p_table'], $r['p_cols'], $r['update'], $r['delete']);
            }
        }

        // Update references
        foreach ($reference_update as $tname => $ref) {
            foreach ($ref as $rname => $r) {
                $schema->alterReference($rname, $r['name'], $tname, $r['c_cols'], $r['p_table'], $r['p_cols'], $r['update'], $r['delete']);
            }
        }

        // Flush execution stack
        $schema->flushStack();

        return count($table_create) + count($key_create) + count($index_create) + count($reference_create) + count($field_create) + count($field_update) + count($key_update) + count($index_update) + count($reference_update);
    }

    /**
     * Get tables.
     *
     * Tables are returned with prefixed name
     *
     * @return array The tables
     */
    public function getTables(): array
    {
        $res = [];
        foreach ($this->tables as $t => $v) {
            $res[$this->prefix . $t] = $v;
        }

        return $res;
    }

    /**
     * Check if a table exsits.
     *
     * @return bool True if table exists
     */
    public function tableExists(string $name): bool
    {
        return isset($this->tables[$name]);
    }

    private function fieldsDiffer(array $db_field, array $schema_field): bool
    {
        $d_type    = $db_field['type'];
        $d_len     = (int) $db_field['len'];
        $d_default = $db_field['default'];
        $d_null    = $db_field['null'];

        $s_type    = $schema_field['type'];
        $s_len     = (int) $schema_field['len'];
        $s_default = $schema_field['default'];
        $s_null    = $schema_field['null'];

        return $d_type != $s_type || $d_len != $s_len || $d_default != $s_default || $d_null != $s_null;
    }

    private function keysDiffer(string $d_name, array|string $d_cols, string $s_name, array|string $s_cols): bool
    {
        return $d_name != $s_name || $d_cols != $s_cols;
    }

    private function indexesDiffer(string $d_name, array $d_i, string $s_name, array $s_i): bool
    {
        return $d_name != $s_name || $d_i['cols'] != $s_i['cols'] || $d_i['type'] != $s_i['type'];
    }

    private function referencesDiffer(string $d_name, array $d_r, string $s_name, array $s_r): bool
    {
        return $d_name != $s_name || $d_r['c_cols'] != $s_r['c_cols'] || $d_r['p_table'] != $s_r['p_table'] || $d_r['p_cols'] != $s_r['p_cols'] || $d_r['update'] != $s_r['update'] || $d_r['delete'] != $s_r['delete'];
    }
}
