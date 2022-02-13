<?php
/**
 * @class Dotclear\Core\Instance\TraitAuth
 * @brief Dotclear trait Configuration
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Instance;

use Dotclear\Core\Instance\Auth;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

trait TraitAuth
{
    /** @var    Auth   Auth instance */
    private $auth;

    /** @var    bool            Instance is initalized */
    public $has_auth = false;

    /**
     * Get auth instance
     *
     * @return  Auth|null  Auth instance or null
     */
    public function auth(): ?Auth
    {
        if (!($this->auth instanceof Auth)) {
            $this->initAuth();
        }

        return $this->auth;
    }

    /**
     * Instanciate authentication
     *
     * @throws  CoreException
     */
    private function initAuth(): void
    {
        # You can set DOTCLEAR_AUTH_CLASS to whatever you want.
        # Your new class *should* inherits Dotclear\Core\Instance\Auth class.
        $class = defined('DOTCLEAR_AUTH_CLASS') ? DOTCLEAR_AUTH_CLASS : __NAMESPACE__ . '\\Auth';

        # Check if auth class exists
        if (!class_exists($class)) {
            throw new CoreException('Authentication class ' . $class . ' does not exist.');
        }

        # Check if auth class inherit Dotclear auth class
        if ($class != __NAMESPACE__ . '\\Auth' && !is_subclass_of($class, __NAMESPACE__ . '\\Auth')) {
            throw new CoreException('Authentication class ' . $class . ' does not inherit ' . __NAMESPACE__ . '\\Auth.');
        }

        $this->has_auth = true;

        $this->auth = new $class();
    }
}
