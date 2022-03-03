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
use Dotclear\Public\Template\Template;
use Dotclear\Public\Context\Context;
use Dotclear\Module\Plugin\Public\ModulesPlugin;
use Dotclear\Module\Theme\Public\ModulesTheme;
use Dotclear\File\Files;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends Core
{
    /** @var    Context     Context instance */
    private $context;

    /** @var    Template    Template instance */
    private $template;

    /** @var    string      Current Process */
    protected $process = 'Public';

    /** @var ModulesPlugin|null ModulesPlugin instance */
    public $plugins = null;

    /** @var ModulesTheme|null  ModulesTheme instance */
    public $themes = null;

    /**
     * Get context instance
     *
     * @return  Context   Context instance
     */
    public function context(): Context
    {
        if (!($this->context instanceof Context)) {
            $this->context = new Context();
        }

        return $this->context;
    }

    /**
     * Get template instance
     *
     * @return  Template   Template instance
     */
    public function template(): Template
    {
        if (!($this->template instanceof Template)) {
            try {
                $this->template = new Template($this->config()->cache_dir, 'dotclear()->template()');
            } catch (\Exception $e) {
                $this->getExceptionLang();
                $this->throwException(__('Unable to create template'), $e->getMessage(), 640);
            }
        }

        return $this->template;
    }

    /**
     * Start Dotclear Public process
     *
     * @param   string  $blog_id    The blog ID
     */
    protected function process(string $blog_id = null): void
    {
        # Load Core Prepend
        parent::process();

        # Add Record extensions
        $this->behavior()->add('coreBlogGetPosts', function (Record $rs): void {
            $rs->extend('Dotclear\\Core\\RsExt\\RsExtPostPublic');
        });
        $this->behavior()->add('coreBlogGetComments', function (Record $rs): void {
            $rs->extend('Dotclear\\Core\\RsExt\\RsExtCommentPublic');
        });

        # Load blog
        $this->setBlog($blog_id ?: '');

        if ($this->blog()->id == null) {
            $this->getExceptionLang();
            $this->throwException(__('Did you change your Blog ID?'), '', 630);
        }

        if ((bool) !$this->blog()->status) {
            $this->unsetBlog();
            $this->getExceptionLang();
            $this->throwException(__('This blog is offline. Please try again later.'), '', 670);
        }

        # Cope with static home page option
        $this->url()->registerDefault(['Dotclear\\Core\\Url\\Url', (bool) $this->blog()->settings()->system->static_home ? 'static_home' : 'home']);

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
            $this->plugins->loadModules($this->_lang);

            # Load loang resources for each plugins
            foreach($this->plugins->getModules() as $module) {
                $this->plugins->loadModuleL10N($module->id(), $this->_lang, 'main');
                $this->plugins->loadModuleL10N($module->id(), $this->_lang, 'public');
            }
        } catch (\Exception $e) {
            $this->getExceptionLang();
            $this->throwException(__('Unable to load plugins.'), $e->getMessage(), 640);
        }

        # Load themes
        try {
            $this->themes = new ModulesTheme();
            $this->themes->loadModules($this->_lang);
        } catch (\Exception $e) {
            $this->getExceptionLang();
            $this->throwException(__('Unable to load themes.'), $e->getMessage(), 640);
        }

        # Load current theme definition
        $path = $this->themes->getThemePath('templates/tpl');

        # If theme doesn't exist, stop everything
        if (!count($path)) {
            $this->getExceptionLang();
            $this->throwException(__('This either means you removed your default theme or set a wrong theme ' .
                    'path in your blog configuration. Please check theme_path value in ' .
                    'about:config module or reinstall default theme. (' . $__theme . ')'), '', 650);
        }

        # Ensure theme's settings namespace exists
        $this->blog()->settings()->addNamespace('themes');

        # If theme has parent load their locales
        if (count($path) > 1) {
            $this->themes->loadModuleL10N(array_key_last($path), $this->_lang, 'main');
            $this->themes->loadModuleL10N(array_key_last($path), $this->_lang, 'public');
        }

        # Themes locales
        $this->themes->loadModuleL10N(array_key_first($path), $this->_lang, 'main');
        $this->themes->loadModuleL10N(array_key_first($path), $this->_lang, 'public');

        # --BEHAVIOR-- publicPrepend
        $this->behavior()->call('publicPrepend');

        # Check templateset and add all path to tpl
        $tplset = $this->themes->getModule(array_key_last($path))->templateset();
        if (!empty($tplset)) {
            $tplset_dir = root_path('Public', 'templates', $tplset);
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
            $this->getExceptionLang();
            $this->throwException(
                __('Something went wrong while loading template file for your blog.'),
                sprintf(__('The following error was encountered while trying to load template file: %s'), $e->getMessage()),
                660
            );
        }
    }
}
