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

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Utils;

use Dotclear\Public\Context;
use Dotclear\Public\Template;

use Dotclear\Module\Plugin\Public\ModulesPlugin;

use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Public';

    public $tpl;

    /** @var ModulesPlugin|null ModulesPlugin instance */
    public $plugins = null;

    /** @var ModulesTheme|null ModulesThemen instance */
    public $themes = null;

    public function __construct(string $blog_id = null)
    {
        # Load Core Prepend
        parent::__construct();

        # Serve modules file (mf)
        $this->publicServeFile();

        # Add Record extensions
        $this->behaviors->add('coreBlogGetPosts', [__CLASS__, 'behaviorCoreBlogGetPosts']);
        $this->behaviors->add('coreBlogGetComments', [__CLASS__, 'behaviorCoreBlogGetComments']);

        # Load blog
        try {
            $this->setBlog($blog_id ?: '');
        } catch (Exception $e) {
            init_prepend_l10n();
            /* @phpstan-ignore-next-line */
            static::error(__('Database problem'), DOTCLEAR_MODE_DEBUG ?
                __('The following error was encountered while trying to read the database:') . '</p><ul><li>' . $e->getMessage() . '</li></ul>' :
                __('Something went wrong while trying to read the database.'), 620);
        }

        if ($this->blog->id == null) {
            static::error(__('Blog is not defined.'), __('Did you change your Blog ID?'), 630);
        }

        if ((boolean) !$this->blog->status) {
            $this->unsetBlog();
            static::error(__('Blog is offline.'), __('This blog is offline. Please try again later.'), 670);
        }

        # Cope with static home page option
        if ($this->blog->settings->system->static_home) {
            $this->url->registerDefault(['Dotclear\\Core\\UrlHandler', 'static_home']);
        }

        # Load media
        try {
            $this->mediaInstance();
        } catch (Exception $e) {
        }

        # Create template context
        $_ctx = new Context();

        try {
            $this->tpl = new Template(DOTCLEAR_CACHE_DIR, '$core->tpl', $this);
        } catch (Exception $e) {
            static::error(__('Can\'t create template files.'), $e->getMessage(), 640);
        }

        # Load locales
        $_lang = $this->blog->settings->system->lang;
        $_lang = preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_lang) ? $_lang : 'en';

        l10n::lang($_lang);
        if (l10n::set(static::path(DOTCLEAR_L10N_DIR, $_lang, 'date')) === false && $_lang != 'en') {
            l10n::set(static::path(DOTCLEAR_L10N_DIR, 'en', 'date'));
        }
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $_lang, 'main'));
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $_lang, 'public'));
        l10n::set(static::path(DOTCLEAR_L10N_DIR, $_lang, 'plugins'));

        # Set lexical lang
        Utils::setlexicalLang('public', $_lang);

        # Load plugins
        try {
            $this->plugins = new ModulesPlugin($this);
            $this->plugins->loadModules($_lang);
        } catch (Exception $e) {
            static::error(__('Can\'t load modules.'), $e->getMessage(), 640);
        }



        echo 'public: public/prepend.php : structure only ';
    }


    public static function behaviorCoreBlogGetPosts($rs)
    {
        $rs->extend('Dotclear\\Core\\RsExt\\RsExtPostPublic');
    }

    public static function behaviorCoreBlogGetComments($rs)
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

        # Serve modules file
        if (empty($_GET['mf'])) {
            return;
        }

        # Extract modules class name from url
        $pos = strpos($_GET['mf'], '/');
        if (!$pos) {
            static::error(__('Failed to load file'), __('File handler not found'), 20);
        }

        # Sanitize modules type
        $type = ucfirst(strtolower(substr($_GET['mf'], 0, $pos)));
        $_GET['mf'] = substr($_GET['mf'], $pos, strlen($_GET['mf']));

        # Check class
        $class = static::ns('Dotclear', 'Module', $type, 'Public', 'Modules' . $type);
        if (!(class_exists($class) && is_subclass_of($class, 'Dotclear\\Module\\AbstractModules'))) {
            static::error(__('Failed to load file'), __('File handler not found'), 20);
        }

        # Get paths and serve file
        $modules = new $class($this);
        $paths   = $modules->getModulesPath();
        $paths[] = static::root('Core', 'files', 'js');
        $paths[] = static::root('Core', 'files', 'css');
        Utils::fileServer($paths, 'mf');
        exit;
    }
}
