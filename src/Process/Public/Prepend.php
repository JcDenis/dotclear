<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Public;

// Dotclear\Process\Public\Prepend
use Dotclear\Core\Core;
use Dotclear\Core\RsExt\RsExtPostPublic;
use Dotclear\Core\RsExt\RsExtCommentPublic;
use Dotclear\Database\Record;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\File\Path;
use Dotclear\Modules\Modules;
use Dotclear\Process\Public\Template\Template;
use Dotclear\Process\Public\Context\Context;
use Exception;

/**
 * Public process.
 *
 * @ingroup  Public
 */
class Prepend extends Core
{
    /**
     * @var Context $context
     *              Context instance
     */
    private $context;

    /**
     * @var Template $template
     *               Template instance
     */
    private $template;

    /**
     * @var null|Modules $plugins
     *                   Plugin Modules instance
     */
    private $plugins;

    /**
     * @var null|Modules $themes
     *                   Theme Modules instance
     */
    private $themes;

    /**
     * @var string $process
     *             Current Process
     */
    protected $process = 'Public';

    /**
     * Get context instance.
     *
     * @return Context Context instance
     */
    public function context(): Context
    {
        if (!($this->context instanceof Context)) {
            $this->context = new Context();
        }

        return $this->context;
    }

    /**
     * Get template instance.
     *
     * @return Template Template instance
     */
    public function template(): Template
    {
        if (!($this->template instanceof Template)) {
            try {
                $this->template = new Template($this->config()->get('cache_dir'), 'App::core()->template()');
            } catch (Exception $e) {
                $this->throwException(__('Unable to create template'), $e->getMessage(), 640, $e);
            }
        }

        return $this->template;
    }

    /**
     * Get plugins instance.
     *
     * @return Modules Plugins Modules instance
     */
    public function plugins(): Modules
    {
        if (!($this->plugins instanceof Modules)) {
            $this->plugins = new Modules(lang: $this->lang);
        }

        return $this->plugins;
    }

    /**
     * Get themes instance.
     *
     * @return Modules Themes Modules instance
     */
    public function themes(): Modules
    {
        if (!($this->themes instanceof Modules)) {
            $this->themes = new Modules(type: 'Theme');
        }

        return $this->themes;
    }

    /**
     * Start Dotclear Public process.
     *
     * @param string $blog_id The blog ID
     */
    protected function process(string $blog_id = null): void
    {
        // Load Core Prepend
        parent::process();

        // Add Record extensions
        $this->behavior()->add('coreBlogGetPosts', function (Record $rs): void {
            $rs->extend(new RsExtPostPublic());
        });
        $this->behavior()->add('coreBlogGetComments', function (Record $rs): void {
            $rs->extend(new RsExtCommentPublic());
        });

        // Load blog
        $this->setBlog($blog_id ?: '');

        if (null == $this->blog()->id) {
            $this->throwException(__('Did you change your Blog ID?'), '', 630);
        }

        if (!$this->blog()->status) {
            $this->unsetBlog();
            $this->throwException(__('This blog is offline. Please try again later.'), '', 670);
        }

        // Cope with static home page option
        $this->url()->registerDefault([
            'Dotclear\\Core\\Url\\Url',
            (bool) $this->blog()->settings()->get('system')->get('static_home') ? 'static_home' : 'home',
        ]);

        // Load locales
        $this->lang($this->blog()->settings()->get('system')->get('lang'));

        if (false === L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang, 'date')) && 'en' != $this->lang) {
            L10n::set(Path::implode($this->config()->get('l10n_dir'), 'en', 'date'));
        }
        L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang, 'main'));
        L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang, 'public'));
        L10n::set(Path::implode($this->config()->get('l10n_dir'), $this->lang, 'plugins'));

        // Set lexical lang
        Lexical::setLexicalLang('public', $this->lang);

        // Load modules
        try {
            $this->plugins();
            $this->themes();
        } catch (Exception $e) {
            $this->throwException(__('Unable to load modules.'), $e->getMessage(), 640, $e);
        }

        // Load current theme definition
        $path = $this->themes->getThemePath('templates/tpl');

        // If theme doesn't exist, stop everything
        if (!count($path)) {
            $this->throwException(__('This either means you removed your default theme or set a wrong theme ' .
                    'path in your blog configuration. Please check theme_path value in ' .
                    'about:config module or reinstall default theme. (Berlin)'), '', 650);
        }

        // If theme has parent load their locales
        if (1 < count($path)) {
            $this->themes->loadModuleL10N(array_key_last($path), (string) $this->lang, 'main');
            $this->themes->loadModuleL10N(array_key_last($path), (string) $this->lang, 'public');
        }

        // Themes locales
        $this->themes->loadModuleL10N(array_key_first($path), (string) $this->lang, 'main');
        $this->themes->loadModuleL10N(array_key_first($path), (string) $this->lang, 'public');

        // --BEHAVIOR-- publicPrepend
        $this->behavior()->call('publicPrepend');

        // Check templateset and add all path to tpl
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

        // Prepare the HTTP cache thing
        $this->url()->mod_files = $this->autoload()->getLoadedFiles();
        $this->url()->mod_ts    = [$this->blog()->upddt];
        $this->url()->mode      = (string) $this->blog()->settings()->get('system')->get('url_scan');

        try {
            // --BEHAVIOR-- publicBeforeDocument
            $this->behavior()->call('publicBeforeDocument');

            $this->url()->getDocument();

            // --BEHAVIOR-- publicAfterDocument
            $this->behavior()->call('publicAfterDocument');
        } catch (Exception $e) {
            $this->throwException(
                __('Something went wrong while loading template file for your blog.'),
                sprintf(__('The following error was encountered while trying to load template file: %s'), $e->getMessage()),
                660,
                $e
            );
        }
    }
}
