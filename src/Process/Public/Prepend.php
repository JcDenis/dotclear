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
use DateTimeZone;
use Dotclear\Core\Core;
use Dotclear\Core\RsExt\RsExtPostPublic;
use Dotclear\Core\RsExt\RsExtCommentPublic;
use Dotclear\Database\Record;
use Dotclear\Exception\InvalidConfiguration;
use Dotclear\Helper\Clock;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Modules\Modules;
use Dotclear\Process\Public\Template\Template;
use Dotclear\Process\Public\Context\Context;
use Exception;

/**
 * Public process.
 *
 * @ingroup  Public
 */
final class Prepend extends Core
{
    /**
     * @var string $timezone
     *             Public interface timezone
     */
    private $timezone;

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
     * @var Modules $plugins
     *              Plugin Modules instance
     */
    private $plugins;

    /**
     * @var Modules $themes
     *              Theme Modules instance
     */
    private $themes;

    /**
     * Get admin default datetime display timezone.
     *
     * This is the user timezone.
     *
     * @return string The user timezone
     */
    public function timezone(): string
    {
        if (!$this->timezone) {
            try {
                $timezone = new DateTimeZone($this->blog() ? $this->blog()->settings('system')->getSetting('blog_timezone') : Clock::getTZ());
                $this->behavior('publicBeforeSetTimezone')->call(timezone: $timezone);
                $this->timezone = $timezone->getName();
            } catch (Exception) {
                $this->timezone = Clock::getTZ();
            }
        }

        return $this->timezone;
    }

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
                throw new InvalidConfiguration(
                    false === $this->isProductionMode() ? $e->getMessage() : __('Unable to create template'),
                    500,
                    $e
                );
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
            $this->plugins = new Modules();
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
     * @param null|string $blog The blog ID (not used)
     */
    public function startProcess(string $blog = null): void
    {
        // Check if configuration complete and app can run
        $this->config()->checkConfiguration();

        // Add top behaviors
        $this->setTopBehaviors();

        // Add Record extensions
        $this->behavior('coreAfterGetPosts')->add(function (Record $record): void {
            $record->extend(new RsExtPostPublic());
        });
        $this->behavior('coreAfterGetComments')->add(function (Record $record): void {
            $record->extend(new RsExtCommentPublic());
        });

        // Load blog
        $this->setBlog($blog ?: '');

        if (null == $this->blog()->id) {
            throw new InvalidConfiguration(__('Did you change your Blog ID?'));
        }

        if (!$this->blog()->status) {
            $this->unsetBlog();

            throw new InvalidConfiguration(__('This blog is offline. Please try again later.'), 503);
        }

        // Cope with static home page option
        $this->url()->setDefaultHandler([
            'Dotclear\\Core\\Url\\Url',
            (bool) $this->blog()->settings('system')->getSetting('static_home') ? 'static_home' : 'home',
        ]);

        // Load locales
        L10n::lang($this->blog()->settings('system')->getSetting('lang'));

        if (false === L10n::set(Path::implode($this->config()->get('l10n_dir'), L10n::lang(), 'date')) && 'en' != L10n::lang()) {
            L10n::set(Path::implode($this->config()->get('l10n_dir'), 'en', 'date'));
        }
        L10n::set(Path::implode($this->config()->get('l10n_dir'), L10n::lang(), 'main'));
        L10n::set(Path::implode($this->config()->get('l10n_dir'), L10n::lang(), 'public'));
        L10n::set(Path::implode($this->config()->get('l10n_dir'), L10n::lang(), 'plugins'));

        // Set lexical lang
        Lexical::setLexicalLang('public', L10n::lang());

        // Load modules
        try {
            $this->plugins();
            $this->themes();
        } catch (Exception $e) {
            throw new InvalidConfiguration(
                false == $this->isProductionMode() ? $e->getMessage() : __('Something went wrong while loading modules.'),
                500,
                $e
            );
        }

        // Load current theme definition
        $path = $this->themes()->getThemePath('templates/tpl');

        // If theme doesn't exist, stop everything
        if (!count($path)) {
            throw new InvalidConfiguration(
                false == $this->isProductionMode() ?
                    __('This either means you removed your default theme or set a wrong theme ' .
                        'path in your blog configuration. Please check theme_path value in ' .
                        'about:config module or reinstall default theme. (Berlin)') :
                    __('Something went wrong while loading theme file for your blog.')
            );
        }

        // If theme has parent load their locales
        if (1 < count($path)) {
            $this->themes()->loadModuleL10N(array_key_last($path), L10n::lang(), 'main');
            $this->themes()->loadModuleL10N(array_key_last($path), L10n::lang(), 'public');
        }

        // Themes locales
        $this->themes()->loadModuleL10N(array_key_first($path), L10n::lang(), 'main');
        $this->themes()->loadModuleL10N(array_key_first($path), L10n::lang(), 'public');

        // --BEHAVIOR-- publicBeforeLoadPage
        $this->behavior('publicBeforeLoadPage')->call();

        // Check templateset and add all path to tpl
        $tplset = $this->themes()->getModule(array_key_last($path))->templateset();
        if (!empty($tplset)) {
            $tplset_dir = Path::implodeSrc('Process', 'Public', 'templates', $tplset);
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
        $this->url()->addModFiles(new Strings(get_included_files()));
        $this->url()->addModTimestamps(new Integers([Clock::ts(date: $this->blog()->upddt, from: $this->getTimezone(), to: 'UTC')]));
        $this->url()->setMode((string) $this->blog()->settings('system')->getSetting('url_scan'));

        try {
            $this->url()->getDocument();
        } catch (Exception $e) {
            throw new InvalidConfiguration(
                false == $this->isProductionMode() ?
                    sprintf(__('The following error was encountered while trying to load template file: %s'), $e->getMessage()) :
                    __('Something went wrong while loading template file for your blog.'),
                500,
                $e
            );
        }
    }
}
