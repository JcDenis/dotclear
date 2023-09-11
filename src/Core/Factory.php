<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Core\Frontend\Url;
use Dotclear\Database\AbstractHandler;
use Dotclear\Module\Plugins;
use Dotclear\Module\Themes;
use Dotclear\Interface\Core\AuthInterface;
use Dotclear\Interface\Core\BehaviorInterface;
use Dotclear\Interface\Core\BlogInterface;
use Dotclear\Interface\Core\BlogLoaderInterface;
use Dotclear\Interface\Core\BlogSettingsInterface;
use Dotclear\Interface\Core\BlogsInterface;
use Dotclear\Interface\Core\BlogWorkspaceInterface;
use Dotclear\Interface\Core\CacheInterface;
use Dotclear\Interface\Core\CategoriesInterface;
use Dotclear\Interface\Core\ConnectionInterface;
use Dotclear\Interface\Core\DeprecatedInterface;
use Dotclear\Interface\Core\ErrorInterface;
use Dotclear\Interface\Core\FactoryInterface;
use Dotclear\Interface\Core\FilterInterface;
use Dotclear\Interface\Core\FormaterInterface;
use Dotclear\Interface\Core\LexicalInterface;
use Dotclear\Interface\Core\LogInterface;
use Dotclear\Interface\Core\MediaInterface;
use Dotclear\Interface\Core\MetaInterface;
use Dotclear\Interface\Core\NonceInterface;
use Dotclear\Interface\Core\NoticeInterface;
use Dotclear\Interface\Core\PostMediaInterface;
use Dotclear\Interface\Core\PostTypesInterface;
use Dotclear\Interface\Core\RestInterface;
use Dotclear\Interface\Core\SessionInterface;
use Dotclear\Interface\Core\TrackbackInterface;
use Dotclear\Interface\Core\UrlInterface;
use Dotclear\Interface\Core\UsersInterface;
use Dotclear\Interface\Core\UserPreferencesInterface;
use Dotclear\Interface\Core\UserWorkspaceInterface;
use Dotclear\Interface\Core\VersionInterface;
use Dotclear\Interface\Module\ModulesInterface;

/**
 * Core default factory.
 *
 * Core factory instanciates main Core classes.
 * The factory should use Core container to get classes
 * required by constructors.
 *
 * Default factory uses Dotclear\Database clases for
 * database connection handler and session handler.
 */
class Factory implements FactoryInterface
{
    /**
     * Constructor takes Container instance.
     *
     * @param   Container   $container The core container
     */
    public function __construct(
        protected Container $container
    ) {
    }

    public function auth(): AuthInterface
    {
        return Auth::init();
    }

    public function behavior(): BehaviorInterface
    {
        return new Behavior();
    }

    public function blog(): BlogInterface
    {
        return $this->container->get('blogLoader')->getBlog();
    }

    public function blogSettings(?string $blog_id): BlogSettingsInterface
    {
        return new BlogSettings(
            blog_id: $blog_id
        );
    }

    public function blogLoader(): BlogLoaderInterface
    {
        return new BlogLoader();
    }

    public function blogs(): BlogsInterface
    {
        return new Blogs();
    }

    public function blogWorkspace(): BlogWorkspaceInterface
    {
        return new BlogWorkspace();
    }

    public function cache(): CacheInterface
    {
        return new Cache(
            cache_dir: defined('DC_TPL_CACHE') ? DC_TPL_CACHE : ''
        );
    }

    public function categories(): CategoriesInterface
    {
        return new Categories();
    }

    public function con(): ConnectionInterface
    {
        return AbstractHandler::init(
            driver: DC_DBDRIVER,
            host: DC_DBHOST,
            database: DC_DBNAME,
            user: DC_DBUSER,
            password: DC_DBPASSWORD,
            persistent: DC_DBPERSIST,
            prefix: DC_DBPREFIX
        );
    }

    public function error(): ErrorInterface
    {
        return new Error();
    }

    public function deprecated(): DeprecatedInterface
    {
        return new Deprecated();
    }

    public function filter(): FilterInterface
    {
        return new Filter();
    }

    public function formater(): FormaterInterface
    {
        return new Formater();
    }

    public function lexical(): LexicalInterface
    {
        return new Lexical();
    }

    public function log(): LogInterface
    {
        return new Log();
    }

    public function media(): MediaInterface
    {
        return new Media();
    }

    public function meta(): MetaInterface
    {
        return new Meta();
    }

    public function nonce(): NonceInterface
    {
        return new Nonce();
    }

    public function notice(): NoticeInterface
    {
        return new Notice();
    }

    public function plugins(): ModulesInterface
    {
        return new Plugins();
    }

    public function postMedia(): PostMediaInterface
    {
        return new PostMedia();
    }

    public function postTypes(): PostTypesInterface
    {
        return new PostTypes();
    }

    public function rest(): RestInterface
    {
        return new Rest();
    }

    public function session(): SessionInterface
    {
        return new Session(
            con: $this->container->get('con'),
            table : $this->container->get('con')->prefix() . Session::SESSION_TABLE_NAME,
            cookie_name: DC_SESSION_NAME,
            cookie_secure: DC_ADMIN_SSL,
            ttl: DC_SESSION_TTL
        );
    }

    public function themes(): ModulesInterface
    {
        return new Themes();
    }

    public function trackback(): TrackbackInterface
    {
        return new Trackback();
    }

    public function url(): UrlInterface
    {
        return new Url();
    }

    public function users(): UsersInterface
    {
        return new Users();
    }

    public function userPreferences(string $user_id, ?string $workspace = null): UserPreferencesInterface
    {
        return new UserPreferences(
            user_id: $user_id,
            workspace: $workspace
        );
    }

    public function userWorkspace(): UserWorkspaceInterface
    {
        return new UserWorkspace();
    }

    public function version(): VersionInterface
    {
        return new Version();
    }
}
