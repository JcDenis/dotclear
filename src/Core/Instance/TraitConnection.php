<?php
/**
 * @class Dotclear\Core\Instance\Connection
 * @brief Dotclear trait database Connection
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Database\Connection;
use Dotclear\Exception\PrependException;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitConnection
{
    /** @var    Connection   Connection instance */
    private $con;

    /** @var string             Database table prefix */
    public $prefix;

    /** @var    bool            Instance is initalized */
    public $has_con = false;

    /**
     * Get Connection instance
     *
     * @return  Connection|null  Connection instance
     */
    public function con(): ?Connection
    {
        if (!($this->con instanceof Connection)) {
            $this->initConnection();
        }

        return $this->con;
    }

    /**
     * Initialize connection
     */
    private function initConnection(): void
    {
        try {
            $this->con = $this->conInstance();
        } catch (\Exception $e) {
            # Loading locales for detected language
            $dlang = Http::getAcceptLanguages();
            foreach ($dlang as $l) {
                if ($l == 'en' || L10n::set(implode_path(dotclear()->config()->l10n_dir, $l, 'main')) !== false) {
                    L10n::lang($l);

                    break;
                }
            }
            if (in_array(DOTCLEAR_PROCESS, ['Admin', 'Install'])) {
                throw new PrependException(
                    __('Unable to connect to database'),
                    $e->getCode() == 0 ?
                    sprintf(
                        __('<p>This either means that the username and password information in ' .
                        'your <strong>config.php</strong> file is incorrect or we can\'t contact ' .
                        'the database server at "<em>%s</em>". This could mean your ' .
                        'host\'s database server is down.</p> ' .
                        '<ul><li>Are you sure you have the correct username and password?</li>' .
                        '<li>Are you sure that you have typed the correct hostname?</li>' .
                        '<li>Are you sure that the database server is running?</li></ul>' .
                        '<p>If you\'re unsure what these terms mean you should probably contact ' .
                        'your host. If you still need help you can always visit the ' .
                        '<a href="https://forum.dotclear.net/">Dotclear Support Forums</a>.</p>') .
                        (dotclear()->config()->run_level >= DOTCLEAR_RUN_DEBUG ? // @phpstan-ignore-line
                            '<p>' . __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                            ''),
                        (dotclear()->config()->database_host != '' ? dotclear()->config()->database_host : 'localhost')
                    ) :
                    '',
                    20
                );
            } else {
                throw new PrependException(
                    __('Site temporarily unavailable'),
                    __('<p>We apologize for this temporary unavailability.<br />' .
                        'Thank you for your understanding.</p>'),
                    20
                );
            }
        }
    }

    /**
     * Instanciate database connection
     *
     * @throws  CoreException
     *
     * @return  Connection      Database connection instance
     */
    private function conInstance(): Connection
    {
        $prefix        = dotclear()->config()->database_prefix;
        $driver        = dotclear()->config()->database_driver;
        $default_class = 'Dotclear\\Database\\Connection';

        # You can set DOTCLEAR_CON_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Database\Connection class.
        $class = defined('DOTCLEAR_CON_CLASS') ? DOTCLEAR_CON_CLASS : $default_class ;

        if (!class_exists($class)) {
            throw new CoreException('Database connection class ' . $class . ' does not exist.');
        }

        if ($class != $default_class && !is_subclass_of($class, $default_class)) {
            throw new CoreException('Database connection class ' . $class . ' does not inherit ' . $default_class);
        }

        # PHP 7.0 mysql driver is obsolete, map to mysqli
        if ($driver === 'mysql') {
            $driver = 'mysqli';
        }

        # Set full namespace of distributed database driver
        if (in_array($driver, ['mysqli', 'mysqlimb4', 'pgsql', 'sqlite'])) {
            $class = 'Dotclear\\Database\\Driver\\' . ucfirst($driver) . '\\Connection';
        }

        # Check if database connection class exists
        if (!class_exists($class)) {
            trigger_error('Unable to load DB layer for ' . $driver, E_USER_ERROR);
            exit(1);
        }

        # Create connection instance
        $con = new $class(
            dotclear()->config()->database_host,
            dotclear()->config()->database_name,
            dotclear()->config()->database_user,
            dotclear()->config()->database_password,
            dotclear()->config()->database_persist
        );

        # Define weak_locks for mysql
        if (in_array($driver, ['mysqli', 'mysqlimb4'])) {
            $con::$weak_locks = true;
        }

        # Define searchpath for postgresql
        if ($driver == 'pgsql') {
            $searchpath = explode('.', $prefix, 2);
            if (count($searchpath) > 1) {
                $prefix = $searchpath[1];
                $sql    = 'SET search_path TO ' . $searchpath[0] . ',public;';
                $con->execute($sql);
            }
        }

        # Set table prefix in core
        $this->prefix = $prefix;

        return $con;
    }
}
