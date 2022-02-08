<?php
/**
 * @class Dotclear\Public\Prepend
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

use function Dotclear\core;

use Dotclear\Exception;
use Dotclear\Exception\PrependException;

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Utils;

use Dotclear\Database\Record;

use Dotclear\Public\Context;
use Dotclear\Public\Template;

use Dotclear\Module\Plugin\Public\ModulesPlugin;
use Dotclear\Module\Theme\Public\ModulesTheme;

use Dotclear\Utils\L10n;
use Dotclear\File\Files;

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
            throw new PrependException(__('Database problem'), DOTCLEAR_RUN_LEVEL >= DOTCLEAR_RUN_DEBUG ?
                __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                __('Something went wrong while trying to read the database.'), 620);
        }

        if ($this->blog->id == null) {
            throw new PrependException(__('Blog is not defined.'), __('Did you change your Blog ID?'), 630);
        }

        if ((boolean) !$this->blog->status) {
            $this->unsetBlog();
            throw new PrependException(__('Blog is offline.'), __('This blog is offline. Please try again later.'), 670);
        }

        # Cope with static home page option
        $this->url->registerDefault(['Dotclear\\Core\\UrlHandler', (bool) $this->blog->settings->system->static_home ? 'static_home' : 'home']);

        # Load media
        try {
            $this->mediaInstance();
        } catch (Exception $e) {
            throw new PrependException(__('Can\'t load media.'), $e->getMessage(), 640);
        }

        # Create template context
        $this->context = new Context();

        try {
            $this->tpl = new Template(DOTCLEAR_CACHE_DIR, '\Dotclear\core()->tpl');
        } catch (Exception $e) {
            throw new PrependException(__('Can\'t create template files.'), $e->getMessage(), 640);
        }

        # Load locales
        $_lang = $this->blog->settings->system->lang;
        $this->_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';

        L10n::lang($this->_lang);
        if (L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            L10n::set(static::path(DOTCLEAR_L10N_DIR, 'en', 'date'));
        }
        L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'main'));
        L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'public'));
        L10n::set(static::path(DOTCLEAR_L10N_DIR, $this->_lang, 'plugins'));

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
            throw new PrependException(__('Can\'t load plugins.'), $e->getMessage(), 640);
        }

        # Load themes
        try {
            $this->themes = new ModulesTheme();
            $this->themes->loadModules($_lang);
        } catch (Exception $e) {
            throw new PrependException(__('Can\'t load themes.'), $e->getMessage(), 640);
        }

        # Load current theme definition
        $path = $this->themes->getThemePath('tpl');

        # If theme has parent load their l10n
        if (count($path) > 1) {
            $this->themes->loadModuleL10N(array_key_last($path), $this->_lang, 'main');
            $this->themes->loadModuleL10N(array_key_last($path), $this->_lang, 'public');
        }

        # If theme doesn't exist, stop everything
        if (!count($path)) {
            throw new PrependException(__('Default theme not found.'), __('This either means you removed your default theme or set a wrong theme ' .
                    'path in your blog configuration. Please check theme_path value in ' .
                    'about:config module or reinstall default theme. (' . $__theme . ')'), 650);
        }

        # Ensure theme's settings namespace exists
        $this->blog->settings->addNamespace('themes');

        # Themes locales
        $this->themes->loadModuleL10N(array_key_first($path), $this->_lang, 'main');
        $this->themes->loadModuleL10N(array_key_first($path), $this->_lang, 'public');

        # --BEHAVIOR-- publicPrepend
        $this->behaviors->call('publicPrepend');

        # Check templateset and add all path to tpl
        $tplset = $this->themes->getModule(array_key_last($path))->templateset();
        if (!empty($tplset)) {
            $tplset_dir = static::path(__DIR__, 'Template', $tplset);
            if (is_dir($tplset_dir)) {
                $this->tpl->setPath($path, $tplset_dir, $this->tpl->getPath());
            } else {
                $tplset = null;
            }
        }
        if (empty($tplset)) {
            $this->tpl->setPath($path, $this->tpl->getPath());
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
            throw new PrependException(__('Template problem'), DOTCLEAR_RUN_LEVEL >= DOTCLEAR_RUN_DEBUG ?
                __('The following error was encountered while trying to load template file:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                __('Something went wrong while loading template file for your blog.'), 660);
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
            Files::serveFile([static::root('Public', 'files')], 'df');
            exit;
        }

        # Serve var file
        if (!empty($_GET['vf'])) {
            Files::serveFile([DOTCLEAR_VAR_DIR], 'vf');
            exit;
        }

        # other files will be served from url handler
    }
}
