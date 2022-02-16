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

use Dotclear\Core\Core;
use Dotclear\Core\Utils;
use Dotclear\Database\Record;
use Dotclear\Exception\PrependException;
use Dotclear\Public\Template;
use Dotclear\Module\Plugin\Public\ModulesPlugin;
use Dotclear\Module\Theme\Public\ModulesTheme;
use Dotclear\File\Files;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends Core
{
    use \Dotclear\Public\Context\TraitContext;
    use \Dotclear\Public\Template\TraitTemplate;

    protected $process = 'Public';

    /** @var ModulesPlugin|null ModulesPlugin instance */
    public $plugins = null;

    /** @var ModulesTheme|null ModulesTheme instance */
    public $themes = null;

    public function process(string $blog_id = null)
    {
        # Serve core files
        $this->publicServeFile();

        # Load Core Prepend
        parent::process();

        # Add Record extensions
        $this->behavior()->add('coreBlogGetPosts', [__CLASS__, 'behaviorCoreBlogGetPosts']);
        $this->behavior()->add('coreBlogGetComments', [__CLASS__, 'behaviorCoreBlogGetComments']);

        # Load blog
        try {
            $this->setBlog($blog_id ?: '');
        } catch (\Exception $e) {
            init_prepend_l10n();
            /* @phpstan-ignore-next-line */
            throw new PrependException(__('Database problem'), $this->config()->run_level >= DOTCLEAR_RUN_DEBUG ?
                __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                __('Something went wrong while trying to read the database.'), 620);
        }

        if ($this->blog()->id == null) {
            throw new PrependException(__('Blog is not defined.'), __('Did you change your Blog ID?'), 630);
        }

        if ((bool) !$this->blog()->status) {
            $this->unsetBlog();
            throw new PrependException(__('Blog is offline.'), __('This blog is offline. Please try again later.'), 670);
        }

        # Cope with static home page option
        $this->url()->registerDefault(['Dotclear\\Core\\Instance\\Url', (bool) $this->blog()->settings()->system->static_home ? 'static_home' : 'home']);

        # Load media
        try {
            $this->media();
        } catch (\Exception $e) {
            throw new PrependException(__('Can\'t load media.'), $e->getMessage(), 640);
        }

        try {
            $this->template($this->config()->cache_dir, 'dotclear()->template()');
        } catch (\Exception $e) {
            throw new PrependException(__('Can\'t create template files.'), $e->getMessage(), 640);
        }

        # Load locales
        $_lang = $this->blog()->settings()->system->lang;
        $this->_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';

        L10n::lang($this->_lang);
        if (L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'date')) === false && $this->_lang != 'en') {
            L10n::set(implode_path($this->config()->l10n_dir, 'en', 'date'));
        }
        L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'main'));
        L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'public'));
        L10n::set(implode_path($this->config()->l10n_dir, $this->_lang, 'plugins'));

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
        } catch (\Exception $e) {
            throw new PrependException(__('Can\'t load plugins.'), $e->getMessage(), 640);
        }

        # Load themes
        try {
            $this->themes = new ModulesTheme();
            $this->themes->loadModules($_lang);
        } catch (\Exception $e) {
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
        $this->blog()->settings()->addNamespace('themes');

        # Themes locales
        $this->themes->loadModuleL10N(array_key_first($path), $this->_lang, 'main');
        $this->themes->loadModuleL10N(array_key_first($path), $this->_lang, 'public');

        # --BEHAVIOR-- publicPrepend
        $this->behavior()->call('publicPrepend');

        # Check templateset and add all path to tpl
        $tplset = $this->themes->getModule(array_key_last($path))->templateset();
        if (!empty($tplset)) {
            $tplset_dir = implode_path(__DIR__, 'Template', 'Template', $tplset);
            if (is_dir($tplset_dir)) {
                $this->template()->setPath($path, $tplset_dir, $this->template()->getPath());
            } else {
                $tplset = null;
            }
        }
        if (empty($tplset)) {
            $this->template()->setPath($path, $this->template()->getPath());
        }

        # Prepare the HTTP cache thing
        $this->url()->mod_files = $this->autoload()->getLoadedFiles();
        $this->url()->mod_ts    = [$this->blog()->upddt];
        $this->url()->mode = (string) $this->blog()->settings()->system->url_scan;

        try {
            # --BEHAVIOR-- publicBeforeDocument
            $this->behavior()->call('publicBeforeDocument');

            $this->url()->getDocument();

            # --BEHAVIOR-- publicAfterDocument
            $this->behavior()->call('publicAfterDocument');
        } catch (\Exception $e) {
            throw new PrependException(__('Template problem'), $this->config()->run_level >= DOTCLEAR_RUN_DEBUG ?
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
            Files::serveFile([root_path('Public', 'files')], 'df');
            exit;
        }

        # Serve var file
        if (!empty($_GET['vf'])) {
            Files::serveFile([$this->config()->var_dir], 'vf');
            exit;
        }

        # other files will be served from url handler
    }
}
