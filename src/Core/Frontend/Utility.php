<?php

/**
 * @package         Dotclear
 * @subpackage      Frontend
 *
 * @defsubpackage   Frontend        Application frontend services
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

/**
 * @namespace   Dotclear.Core.Frontend
 * @brief       Dotclear application frontend utilities.
 */

namespace Dotclear\Core\Frontend;

use dcCore;
use Dotclear\App;
use Dotclear\Core\Utility as AbstractUtility;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\L10n;
use Dotclear\Exception\BlogException;
use Dotclear\Exception\ContextException;
use Dotclear\Exception\TemplateException;
use Dotclear\Schema\Extension\CommentPublic;
use Dotclear\Schema\Extension\PostPublic;
use Throwable;

/**
 * Utility class for public context.
 */
class Utility extends AbstractUtility
{
    public const CONTAINER_ID = 'Frontend';

    /**
     * The default templates folder name
     *
     * @var    string  TPL_ROOT
     */
    public const TPL_ROOT = 'default-templates';

    /**
     * Searched term
     *
     * @var string|null     $search
     */
    public $search;

    /**
     * Searched count
     *
     * @var string      $search_count
     */
    public $search_count;

    /**
     * Current theme
     *
     * @var mixed   $theme
     */
    public $theme;

    /**
     * Current theme's parent, if any
     *
     * @var mixed   $parent_theme
     */
    public $parent_theme;

    /**
     * Is current theme overloadable?
     */
    public bool $theme_overload;

    /**
     * Smilies definitions
     *
     * @var array<string, string>    $smilies
     */
    public array $smilies;

    /**
     * Current page number
     *
     * @var int     $page_number
     */
    protected $page_number;

    /**
     * Constructs a new instance.
     *
     * @throws     ContextException  (if not public context)
     */
    public function __construct()
    {
        if (!App::task()->checkContext('FRONTEND')) {
            throw new ContextException('Application is not in public context.');
        }

        // deprecated since 2.28, use App::frontend() instead
        dcCore::app()->public = $this;

        if (App::task()->checkContext('BACKEND') && App::session()->exists()) {
            // Opening a Frontend context inside a Backend one, nothing more to do
        } elseif (App::blog()->id() === '') {
            // configure session with default paremeters, use case in FileServer
            App::session()->configure(
                cookie_name: App::config()->sessionName(),
                cookie_secure: App::config()->adminSsl(),
                ttl: App::config()->sessionTtl()
            );
        } else {
            // Take care of blog URL for frontend session
            $url = parse_url(App::blog()->url());
            if (!is_array($url)) {
                throw new BlogException(__('Something went wrong while trying to read blog URL.')) ;
            }

            // configure frontend session
            App::session()->configure(
                cookie_name: App::config()->sessionName() . '_' . App::blog()->id(),
                cookie_path: isset($url['path']) ? dirname($url['path']) : '',
                //cookie_domain: null,
                cookie_secure: empty($url['scheme']) || !preg_match('%^http[s]?$%', $url['scheme']) ? false : $url['scheme'] === 'https',
                ttl: App::config()->sessionTtl()
            );
        }

        // Load utility container
        parent::__construct();
    }

    public function getDefaultServices(): array
    {
        return [
            Ctx::class => Ctx::class,
            Tpl::class => Tpl::class,
        ];
    }

    /**
     * Get frontend context instance.
     *
     * @return  Ctx     The context
     */
    public function context(): Ctx
    {
        return $this->get(Ctx::class);
    }

    /**
     * Get frontend template instance.
     *
     * @return  Tpl     The template instance
     */
    public function template(): Tpl
    {
        try {
            return $this->get(Tpl::class, false, App::config()->cacheRoot(), 'App::frontend()->template()');
        } catch (Throwable $e) {
            throw new TemplateException(__('Can\'t create template files.'), TemplateException::code(), $e);
        }
    }

    /**
     * Instanciate this as a singleton and initializes the context.
     *
     * @throws     BlogException|TemplateException
     */
    public static function process(): bool
    {
        // Loading blog
        if (App::config()->blogId() !== '') {
            try {
                App::blog()->loadFromBlog(App::config()->blogId());
            } catch (Throwable) {
                throw new BlogException(__('Something went wrong while trying to read the database.'));
            }
        }

        if (!App::blog()->isDefined()) {
            throw new BlogException(__('Blog is not defined.'), 404);
        }

        if (App::status()->comment()->isRestricted(App::blog()->status())) {
            App::blog()->loadFromBlog('');

            throw new BlogException(__('This blog is offline. Please try again later.'), 404);
        }

        // Instanciate Frontend instance
        App::frontend();

        // Load some class extents and set some public behaviors (was in public prepend before)
        App::behavior()->addBehaviors([
            'publicHeadContent' => function (): string {
                if (!App::blog()->settings()->system->no_public_css) {
                    echo App::plugins()->cssLoad(App::blog()->getQmarkURL() . 'pf=public.css');
                }
                if (App::blog()->settings()->system->use_smilies) {
                    echo App::plugins()->cssLoad(App::blog()->getQmarkURL() . 'pf=smilies.css');
                }
                if (!App::blog()->settings()->system->allow_ai_tdm) {
                    echo '<meta name="tdm-reservation" content="1">' . "\n";
                }

                return '';
            },
            'coreBlogGetPosts' => function (MetaRecord $rs): string {
                $rs->extend(PostPublic::class);

                return '';
            },
            'coreBlogGetComments' => function (MetaRecord $rs): string {
                $rs->extend(CommentPublic::class);

                return '';
            },
        ]);

        /*
         * @var        integer
         *
         * @deprecated Since 2.24
         */
        $GLOBALS['_page_number'] = 0;

        # Check blog sleep mode
        App::blog()->checkSleepmodeTimeout();

        # Cope with static home page option
        if (App::blog()->settings()->system->static_home) {
            App::url()->registerDefault(App::url()::static_home(...));
        }

        // deprecated since 2.28, need to load dcCore::app()->media
        App::media();

        // deprecated since 2.28, use App::frontend()->context() instead
        dcCore::app()->ctx = App::frontend()->context();

        // deprecated since 2.23, use App::frontend()->context() instead
        $GLOBALS['_ctx'] = App::frontend()->context();

        // deprecated since 2.28, use App::frontend()->template() instead
        dcCore::app()->tpl =  App::frontend()->template();

        # Loading locales
        App::lang()->setLang((string) App::blog()->settings()->system->lang);

        if (L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/date') === false && App::lang()->getLang() !== 'en') {
            L10n::set(App::config()->l10nRoot() . '/en/date');
        }
        L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/public');
        L10n::set(App::config()->l10nRoot() . '/' . App::lang()->getLang() . '/plugins');

        // Set lexical lang
        App::lexical()->setLexicalLang('public', App::lang()->getLang());

        # Loading plugins
        try {
            App::plugins()->loadModules(App::config()->pluginsRoot(), 'public', App::lang()->getLang());
        } catch (Throwable $e) {
            if (App::config()->debugMode() || App::config()->devMode()) {
                throw $e;
            }
        }

        // deprecated since 2.28, use App::themes() instead
        dcCore::app()->themes = App::themes();

        # Loading themes
        App::themes()->loadModules(App::blog()->themesPath());

        # Defining theme if not defined
        if (App::frontend()->theme === null) {
            App::frontend()->theme = App::blog()->settings()->system->theme;
        }

        if (!App::themes()->moduleExists(App::frontend()->theme)) {
            App::frontend()->theme = App::blog()->settings()->system->theme = App::config()->defaultTheme();
        }

        App::frontend()->parent_theme = App::themes()->moduleInfo(App::frontend()->theme, 'parent');
        if (is_string(App::frontend()->parent_theme) && (App::frontend()->parent_theme !== null && App::frontend()->parent_theme !== '') && !App::themes()->moduleExists(App::frontend()->parent_theme)) {
            // Parent theme defined but not installed, fallback theme to default one
            App::frontend()->theme        = App::blog()->settings()->system->theme = App::config()->defaultTheme();
            App::frontend()->parent_theme = null;
        }

        # If theme doesn't exist, stop everything
        if (!App::themes()->moduleExists(App::frontend()->theme)) {
            throw new TemplateException(App::config()->debugMode() ?
                __('This either means you removed your default theme or set a wrong theme ' .
                'path in your blog configuration. Please check theme_path value in ' .
                'about:config module or reinstall default theme. (' . App::frontend()->theme . ')') :
                __('Default theme not found.'));
        }

        # Loading _public.php file for selected theme
        App::themes()->loadNsFile(App::frontend()->theme, 'public');

        # Loading translations for selected theme
        if (is_string(App::frontend()->parent_theme) && (App::frontend()->parent_theme !== null && App::frontend()->parent_theme !== '')) {
            App::themes()->loadModuleL10N(App::frontend()->parent_theme, App::lang()->getLang(), 'main');
        }
        App::themes()->loadModuleL10N(App::frontend()->theme, App::lang()->getLang(), 'main');

        # --BEHAVIOR-- publicPrepend --
        App::behavior()->callBehavior('publicPrependV2');

        # Prepare the HTTP cache thing
        App::cache()->addFiles(get_included_files());
        App::cache()->addTime(App::blog()->upddt());

        // deprecated Since 2.23, use App::cache()->addFiles() or App::cache()->getFiles() instead
        $GLOBALS['mod_files'] = App::cache()->getFiles();

        // deprecated Since 2.23, use App::cache()->addTimes() or App::cache()->getTimes) instead
        $GLOBALS['mod_ts'] = App::cache()->getTimes();

        $tpl_path = [
            App::config()->varRoot() . '/themes/' . App::config()->blogId() . '/' . App::frontend()->theme . '/tpl',
            App::blog()->themesPath() . '/' . App::frontend()->theme . '/tpl',
        ];
        if (App::frontend()->parent_theme) {
            $tpl_path[] = App::blog()->themesPath() . '/' . App::frontend()->parent_theme . '/tpl';
        }
        $tplset = App::themes()->moduleInfo(App::frontend()->theme, 'tplset');
        $dir    = implode(DIRECTORY_SEPARATOR, [App::config()->dotclearRoot(), 'inc', 'public', self::TPL_ROOT, $tplset]);
        if (!empty($tplset) && is_dir($dir)) {
            App::frontend()->template()->setPath(
                $tpl_path,
                $dir,
                App::frontend()->template()->getPath()
            );
        } else {
            App::frontend()->template()->setPath(
                $tpl_path,
                App::frontend()->template()->getPath()
            );
        }

        // Check if the current theme may be overload (using theme file handler, index.php?tf=...)
        App::frontend()->theme_overload = App::themes()->isOverloadable(App::frontend()->theme);

        // Set URL scan mode
        App::url()->setMode(App::blog()->settings()->system->url_scan);

        try {
            # --BEHAVIOR-- publicBeforeDocument --
            App::behavior()->callBehavior('publicBeforeDocumentV2');

            App::url()->getDocument();

            # --BEHAVIOR-- publicAfterDocument --
            App::behavior()->callBehavior('publicAfterDocumentV2');
        } catch (Throwable $e) {
            throw new TemplateException(__('Something went wrong while loading template file for your blog.'), TemplateException::code(), $e);
        }

        // Do not try to execute a process added to the URL.
        return false;
    }

    /**
     * Sets the page number.
     *
     * @param      int  $value  The value
     */
    public function setPageNumber(int $value): void
    {
        $this->page_number = $value;

        /*
         * @deprecated since 2.24, may be removed in near future
         *
         * @var int
         */
        $GLOBALS['_page_number'] = $value;
    }

    /**
     * Gets the page number.
     *
     * @return     int   The page number.
     */
    public function getPageNumber(): int
    {
        return (int) $this->page_number;
    }
}
