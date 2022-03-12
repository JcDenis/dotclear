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

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Session
{
    private $table;
    private $cookie_name;
    private $cookie_path;
    private $cookie_domain;
    private $cookie_secure;
    private $ttl       = '-120 minutes';
    private $transient = false;

    /**
     * Constructor
     *
     * This method creates an instance of sessionDB class.
     */
    public function __construct()
    {
        $this->table         = dotclear()->prefix . 'session';
        $this->cookie_name   = dotclear()->config()->session_name;
        $this->cookie_path   = '/';
        $this->cookie_domain = '';
        $this->cookie_secure = dotclear()->config()->admin_ssl;
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
     *
     * @return  string|null  The TTL
     */
    private function getTTL(): void
    {
        # Session time
        $ttl = dotclear()->config()->session_ttl;
        if (!is_null($ttl)) {   // @phpstan-ignore-line
            $tll = (string) $ttl;
            if (substr(trim($ttl), 0, 1) != '-') {
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
    public function start()
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
    public function destroy()
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
     * @param boolean     $transient     Session transient flag
     */
    public function setTransientSession($transient = false)
    {
        $this->transient = $transient;
    }

    /**
     * Session Cookie
     *
     * This method returns an array of all session cookie parameters.
     *
     * @param mixed        $value        Cookie value
     * @param integer    $expire        Cookie expiration timestamp
     */
    public function getCookieParameters($value = null, $expire = 0)
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

    public function _open($path, $name)
    {
        return true;
    }

    public function _close()
    {
        $this->_gc();

        return true;
    }

    public function _read($ses_id)
    {
        $strReq = 'SELECT ses_value FROM ' . $this->table . ' ' .
        'WHERE ses_id = \'' . $this->checkID($ses_id) . '\' ';

        $rs = dotclear()->con()->select($strReq);

        if ($rs->isEmpty()) {
            return '';
        }

        return $rs->f('ses_value');
    }

    public function _write($ses_id, $data)
    {
        $strReq = 'SELECT ses_id ' .
        'FROM ' . $this->table . ' ' .
        "WHERE ses_id = '" . $this->checkID($ses_id) . "' ";

        $rs = dotclear()->con()->select($strReq);

        $cur            = dotclear()->con()->openCursor($this->table);
        $cur->ses_time  = (string) time();
        $cur->ses_value = (string) $data;

        if (!$rs->isEmpty()) {
            $cur->update("WHERE ses_id = '" . $this->checkID($ses_id) . "' ");
        } else {
            $cur->ses_id    = (string) $this->checkID($ses_id);
            $cur->ses_start = (string) time();

            $cur->insert();
        }

        return true;
    }

    public function _destroy($ses_id)
    {
        $strReq = 'DELETE FROM ' . $this->table . ' ' .
        'WHERE ses_id = \'' . $this->checkID($ses_id) . '\' ';

        dotclear()->con()->execute($strReq);

        if (!$this->transient) {
            $this->_optimize();
        }

        return true;
    }

    public function _gc()
    {
        $ses_life = strtotime($this->ttl);

        $strReq = 'DELETE FROM ' . $this->table . ' ' .
            'WHERE ses_time < ' . $ses_life . ' ';

        dotclear()->con()->execute($strReq);

        if (dotclear()->con()->changes() > 0) {
            $this->_optimize();
        }

        return true;
    }

    private function _optimize()
    {
        dotclear()->con()->vacuum($this->table);
    }

    private function checkID($id)
    {
        if (!preg_match('/^([0-9a-f]{40})$/i', (string) $id)) {
            return;
        }

        return $id;
    }
}
