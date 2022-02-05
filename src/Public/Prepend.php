<?php
/**
 * @brief Dotclear public core prepend class
 *
 * @package Dotclear
 * @subpackage Public
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Public;

use Dotclear\Exception;

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Utils;

use Dotclear\Database\Record;

use Dotclear\Public\Context;
use Dotclear\Public\Template;

use Dotclear\Module\Plugin\Public\ModulesPlugin;
use Dotclear\Module\Theme\Public\ModulesTheme;

use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Public';

    /** @var ModulesPlugin|null ModulesPlugin instance */
    public $plugins = null;

    /** @var ModulesTheme|null ModulesTheme instance */
    public $themes = null;

    public $tpl;
    public $context;

    public function process(string $blog_id = null)
    {
        # Serve core files
        $this->publicServeFile();

        # Load Core Prepend
        parent::process();

        # Add Record extensions
        $this->behaviors->add('coreBlogGetPosts', [__CLASS__, 'behaviorCoreBlogGetPosts']);
        $this->behaviors->add('coreBlogGetComments', [__CLASS__, 'behaviorCoreBlogGetComments']);

        # Load blog
        try {
            $this->setBlog($blog_id ?: '');
        } catch (Exception $e) {
            init_prepend_l10n();
            /* @phpstan-ignore-next-line */
            static::errorpage(__('Database problem'), DOTCLEAR_MODE_DEBUG ?
                __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                __('Something went wrong while trying to read the database.'), 620);
        }

        if ($this->blog->id == null) {
            static::errorpage(__('Blog is not defined.'), __('Did you change your Blog ID?'), 630);
        }

        if ((boolean) !$this->blog->status) {
            $this->unsetBlog();
            static::errorpage(__('Blog is offline.'), __('This blog is offline. Please try again later.'), 670);
        }

        # Cope with static home page option
        $this->url->registerDefault(['Dotclear\\Core\\UrlHandler', (bool) $this->blog->settings->system->static_home ? 'static_home' : 'home']);

        # Load media
        try {
            $this->mediaInstance();
        } catch (Exception $e) {
            static::errorpage(__('Can\'t load media.'), $e->getMessage(), 640);
        }

        # Create template context
        $this->context = new Context();

        try {
            $this->tpl = new Template(DOTCLEAR_CACHE_DIR, 'dcCore()->tpl');
        } catch (Exception $e) {
            static::errorpage(__('Can\'t create template files.'), $e->getMessage(), 640);
        }

        # Load locales
        $_lang = $this->blog->settings->system->lang;
        $this->_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';

        l10n::lang($this->_lang);
        if (l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            l10n::set(static::path(DOTCLEAR_L10N_DIR, 'en', 'date'));
        }
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'main'));
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'public'));
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'plugins'));

        # Set lexical lang
        Utils::setlexicalLang('public', $this->_lang);

        # Load plugins
        try {
            $this->plugins = new ModulesPlugin();
            $this->plugins->loadModules($_lang);

            # Load loang resources for each plugins
            foreach($this->plugins->getModules() as $module) {
                $this->plugins->loadModuleL10N($module->id(), $this->_lang, 'main');
                $this->plugins->loadModuleL10N($module->id(), $this->_lang, 'public');
            }
        } catch (Exception $e) {
            static::errorpage(__('Can\'t load plugins.'), $e->getMessage(), 640);
        }

        # Load themes
        try {
            $this->themes = new ModulesTheme();
            $this->themes->loadModules($_lang);
        } catch (Exception $e) {
            static::errorpage(__('Can\'t load themes.'), $e->getMessage(), 640);
        }

        # Load current theme definition
        $__parent_theme = null;
        $__theme = $this->themes->getModule((string) $this->blog->settings->system->theme);
        if (!$__theme) {
            $__theme = $this->themes->getModule('BlowUp');
        # Load parent theme definition
        } elseif ($__theme->parent()) {
            $__parent_theme = $this->themes->getModule((string) $__theme->parent());
            if (!$__parent_theme) {
                $__theme = $this->themes->getModule('BlowUp');
                $__parent_theme = null;
            } else {
                $this->themes->loadModuleL10N($__parent_theme->id(), $this->_lang, 'main');
                $this->themes->loadModuleL10N($__parent_theme->id(), $this->_lang, 'public');
            }
        }

        # If theme doesn't exist, stop everything
        if (!$__theme) {
            static::errorpage(__('Default theme not found.'), __('This either means you removed your default theme or set a wrong theme ' .
                    'path in your blog configuration. Please check theme_path value in ' .
                    'about:config module or reinstall default theme. (' . $__theme . ')'), 650);
        }

        # Ensure theme's settings namespace exists
        $this->blog->settings->addNamespace('themes');

        # Themes locales
        $this->themes->loadModuleL10N($__theme->id(), $this->_lang, 'main');
        $this->themes->loadModuleL10N($__theme->id(), $this->_lang, 'public');

        # --BEHAVIOR-- publicPrepend
        $this->behaviors->call('publicPrepend');

        $__theme_tpl_path = [
            static::path($__theme->root(), 'tpl')
        ];
        if ($__parent_theme) {
            $__theme_tpl_path[] = static::path($__parent_theme->root(), 'tpl');
        }
        $tplset = $__theme->templateset();
        if (!empty($tplset)) {
            $tplset_dir = static::path(__DIR__, 'Template', $tplset);
            if (is_dir($tplset_dir)) {
                $this->tpl->setPath($__theme_tpl_path, $tplset_dir, $this->tpl->getPath());
            } else {
                $tplset = null;
            }
        }
        if (empty($tplset)) {
            $this->tpl->setPath($__theme_tpl_path, $this->tpl->getPath());
        }

        # Prepare the HTTP cache thing
        $this->url->mod_files = $this->autoloader->getLoadedFiles();
        $this->url->mod_ts    = [$this->blog->upddt];
        $this->url->mode = (string) $this->blog->settings->system->url_scan;

        try {
            # --BEHAVIOR-- publicBeforeDocument
            $this->behaviors->call('publicBeforeDocument');

            $this->url->getDocument();

            # --BEHAVIOR-- publicAfterDocument
            $this->behaviors->call('publicAfterDocument');
        } catch (Exception $e) {
            if (DOTCLEAR_MODE_DEV) {
                throw $e;
            }
            static::errorpage($e->getMessage(), __('Something went wrong while loading template file for your blog.'), 660);
        }
    }


    public static function behaviorCoreBlogGetPosts(Record $rs)
    {
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtPostPublic');
    }

    public static function behaviorCoreBlogGetComments(Record $rs)
    {
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtCommentPublic');
    }

    private function publicServeFile(): void
    {
        # Serve admin file (css, png, ...)
        if (!empty($_GET['df'])) {
            Utils::fileServer([static::root('Public', 'files')], 'df');
            exit;
        }

        # Serve var file
        if (!empty($_GET['vf'])) {
            Utils::fileServer([DOTCLEAR_VAR_DIR], 'vf');
            exit;
        }

        # other files will be served from url handler
    }
}
