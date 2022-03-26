<?php
/**
 * @class Dotclear\Process\Public\Prepend
 * @brief Dotclear public core prepend class
 *
 * @package Dotclear
 * @subpackage Public
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public;

use Dotclear\Core\Core;
use Dotclear\Core\RsExt\RsExtPostPublic;
use Dotclear\Core\RsExt\RsExtCommentPublic;
use Dotclear\Database\Record;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Module\AbstractModules;
use Dotclear\Process\Public\Template\Template;
use Dotclear\Process\Public\Context\Context;

class Prepend extends Core
{
    /** @var    Context     Context instance */
    private $context;

    /** @var    Template    Template instance */
    private $template;

    /** @var    AbstractModules|null    ModulesPlugin instance */
    private $plugins = null;

    /** @var    AbstractModules|null    ModulesTheme instance */
    private $themes = null;

    /** @var    string      Current Process */
    protected $process = 'Public';

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
                $this->throwException(__('Unable to create template'), $e->getMessage(), 640, $e);
            }
        }

        return $this->template;
    }

    public function plugins(): ?AbstractModules
    {
        return $this->plugins;
    }

    public function themes(): ?AbstractModules
    {
        return $this->themes;
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
            $rs->extend(new RsExtPostPublic());
        });
        $this->behavior()->add('coreBlogGetComments', function (Record $rs): void {
            $rs->extend(new RsExtCommentPublic());
        });

        # Load blog
        $this->setBlog($blog_id ?: '');

        if ($this->blog()->id == null) {
            $this->throwException(__('Did you change your Blog ID?'), '', 630);
        }

        if ((bool) !$this->blog()->status) {
            $this->unsetBlog();
            $this->throwException(__('This blog is offline. Please try again later.'), '', 670);
        }

        # Cope with static home page option
        $this->url()->registerDefault([
            'Dotclear\\Core\\Url\\Url',
            (bool) $this->blog()->settings()->system->static_home ? 'static_home' : 'home'
        ]);

        # Load locales
        $this->lang($this->blog()->settings()->system->lang);

        if (L10n::set(Path::implode($this->config()->l10n_dir, $this->lang, 'date')) === false && $this->lang != 'en') {
            L10n::set(Path::implode($this->config()->l10n_dir, 'en', 'date'));
        }
        L10n::set(Path::implode($this->config()->l10n_dir, $this->lang, 'main'));
        L10n::set(Path::implode($this->config()->l10n_dir, $this->lang, 'public'));
        L10n::set(Path::implode($this->config()->l10n_dir, $this->lang, 'plugins'));

        # Set lexical lang
        Lexical::setLexicalLang('public', $this->lang);

        # Load modules
        try {
            $types = [
                [&$this->plugins, $this->config()->plugin_dirs, '\\Dotclear\\Module\\Plugin\\Public\\ModulesPlugin', $this->lang],
                [&$this->themes, $this->config()->theme_dirs, '\\Dotclear\\Module\\Theme\\Public\\ModulesTheme', null],
            ];
            foreach($types as $t) {
                # Modules directories
                if (!empty($t[1])) {
                    # Load Modules instance
                    $t[0] = new $t[2]($t[3]);
                }
            }
        } catch (\Exception $e) {
            $this->throwException(__('Unable to load modules.'), $e->getMessage(), 640, $e);
        }

        # Load current theme definition
        $path = $this->themes->getThemePath('templates/tpl');

        # If theme doesn't exist, stop everything
        if (!count($path)) {
            $this->throwException(__('This either means you removed your default theme or set a wrong theme ' .
                    'path in your blog configuration. Please check theme_path value in ' .
                    'about:config module or reinstall default theme. (' . $__theme . ')'), '', 650);
        }

        # If theme has parent load their locales
        if (count($path) > 1) {
            $this->themes->loadModuleL10N(array_key_last($path), $this->lang, 'main');
            $this->themes->loadModuleL10N(array_key_last($path), $this->lang, 'public');
        }

        # Themes locales
        $this->themes->loadModuleL10N(array_key_first($path), $this->lang, 'main');
        $this->themes->loadModuleL10N(array_key_first($path), $this->lang, 'public');

        # --BEHAVIOR-- publicPrepend
        $this->behavior()->call('publicPrepend');

        # Check templateset and add all path to tpl
        $tplset = $this->themes->getModule(array_key_last($path))->templateset();
        if (!empty($tplset)) {
            $tplset_dir = Path::implodeRoot('Process', 'Public', 'templates', $tplset);
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
            $this->throwException(
                __('Something went wrong while loading template file for your blog.'),
                sprintf(__('The following error was encountered while trying to load template file: %s'), $e->getMessage()),
                660,
                $e
            );
        }
    }
}
