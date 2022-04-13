<?php
/**
 * @class Dotclear\Core\Session\Session
 * @brief Dotclear core session class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Session;

use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;

class Session
{
    /** @var    string  $table  Session table name */
    private $table;

    /** @var    string  $cookie_name    Session cookie name */
    private $cookie_name;

    /** @var    string  $cookie_path    Session cookie path */
    private $cookie_path;

    /** @var    string  $cookie_domain  Session cookie domani */
    private $cookie_domain;

    /** @var    string  $cookie_secure  Session cookie is secure */
    private $cookie_secure;

    /** @var    string  $ttl    Session time to live */
    private $ttl = '-120 minutes';

    /** @var    bool    $transient  Session transient */
    private $transient = false;

    /**
     * Constructor
     *
     * This method creates an instance of sessionDB class.
     */
    public function __construct()
    {
        $this->table         = dotclear()->prefix . 'session';
        $this->cookie_name   = dotclear()->config()->get('session_name');
        $this->cookie_path   = '/';
        $this->cookie_domain = '';
        $this->cookie_secure = dotclear()->config()->get('admin_ssl');
        $this->getTTL();

        if (function_exists('ini_set')) {
            @ini_set('session.use_cookies', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('url_rewriter.tags', '');
            @ini_set('session.use_trans_sid', '0');
            @ini_set('session.cookie_path', $this->cookie_path);
            @ini_set('session.cookie_domain', $this->cookie_domain);
            @ini_set('session.cookie_secure', (string) $this->cookie_secure);
        }
    }

    /**
     * Get session ttl
     */
    private function getTTL(): void
    {
        # Session time
        $ttl = dotclear()->config()->get('session_ttl');
        if (!is_null($ttl)) {
            $tll = (string) $ttl;
            if ('-' != substr(trim($ttl), 0, 1)) {
                // We requires negative session TTL
                $ttl = '-' . trim($ttl);
            }
        }
        if (!is_null($ttl)) {
            $this->ttl = $ttl;
        }
    }

    /**
     * Destructor
     *
     * This method calls session_write_close PHP function.
     */
    public function __destruct()
    {
        if (isset($_SESSION)) {
            session_write_close();
        }
    }

    /**
     * Session Start
     */
    public function start(): void
    {
        session_set_save_handler(
            [&$this, '_open'],
            [&$this, '_close'],
            [&$this, '_read'],
            [&$this, '_write'],
            [&$this, '_destroy'],
            [&$this, '_gc']
        );

        if (isset($_SESSION) && session_name() != $this->cookie_name) {
            $this->destroy();
        }

        if (!isset($_COOKIE[$this->cookie_name])) {
            session_id(sha1(uniqid((string) rand(), true)));
        }

        session_name($this->cookie_name);
        session_start();
    }

    /**
     * Session Destroy
     *
     * This method destroies all session data and removes cookie.
     */
    public function destroy(): void
    {
        $_SESSION = [];
        session_unset();
        session_destroy();
        call_user_func_array('setcookie', $this->getCookieParameters(false, -600));
    }

    /**
     * Session Transient
     *
     * This method set the transient flag of the session
     *
     * @param   bool    $transient  Session transient flag
     */
    public function setTransientSession(bool $transient = false): void
    {
        $this->transient = $transient;
    }

    /**
     * Session Cookie
     *
     * This method returns an array of all session cookie parameters.
     *
     * @param   mixed   $value      Cookie value
     * @param   int     $expire     Cookie expiration timestamp
     * 
     * @return  array               All cookie params
     */
    public function getCookieParameters(mixed $value = null, int $expire = 0): array
    {
        return [
            session_name(),
            $value,
            $expire,
            $this->cookie_path,
            $this->cookie_domain,
            $this->cookie_secure,
        ];
    }

    public function _open(string $path, string $name): bool
    {
        return true;
    }

    public function _close(): bool
    {
        $this->_gc();

        return true;
    }

    public function _read(string $ses_id): string
    {
        $rs = SelectStatement::init(__METHOD__)
            ->columns(['ses_value'])
            ->from($this->table)
            ->where("ses_id = '" . $this->checkID($ses_id) . "'")
            ->select();

        return $rs->isEmpty() ? '' : $rs->f('ses_value');
    }

    public function _write(string $ses_id, string $data): bool
    {
        $ses_id = $this->checkID($ses_id);

        $rs = SelectStatement::init(__METHOD__)
            ->columns(['ses_id'])
            ->from($this->table)
            ->where("ses_id = '" . $ses_id . "'")
            ->select();

        if (!$rs->isEmpty()) {
            $sql = new UpdateStatement(__METHOD__);
            $sql
                ->from($this->table)
                ->where('ses_id = ' . $sql->quote($ses_id))
                ->set('ses_time = ' . time())
                ->set('ses_value = ' . $sql->quote($data))
                ->update();
        } else {
            $sql = new InsertStatement(__METHOD__);
            $sql
                ->from($this->table)
                ->columns([
                    'ses_id',
                    'ses_start',
                    'ses_time',
                    'ses_value',
                ])
                ->line([[
                    $sql->quote($ses_id),
                    time(),
                    time(),
                    $sql->quote($data),
                ]])
                ->insert();
        }

        return true;
    }

    public function _destroy(string $ses_id): bool
    {
        DeleteStatement::init(__METHOD__)
            ->from($this->table)
            ->where("ses_id = '" . $this->checkID($ses_id) . "'")
            ->delete();

        if (!$this->transient) {
            $this->_optimize();
        }

        return true;
    }

    public function _gc(): bool
    {
        DeleteStatement::init(__METHOD__)
            ->from($this->table)
            ->where('ses_time < ' . strtotime($this->ttl))
            ->delete();

        if (0 < dotclear()->con()->changes()) {
            $this->_optimize();
        }

        return true;
    }

    private function _optimize(): void
    {
        dotclear()->con()->vacuum($this->table);
    }

    private function checkID(string $id): ?string
    {
        return !preg_match('/^([0-9a-f]{40})$/i', (string) $id) ? null : $id;
    }
}
