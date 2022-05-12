<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Resource;

// Dotclear\Process\Admin\Resource\Resource
use Dotclear\App;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Modules\Modules;

/**
 * Admin file URL helper.
 *
 * @ingroup  Admin Stack
 */
class Resource
{
    /**
     * @var array<string,bool> $stack
     *                         Stack to keep track of loaded files
     */
    private $stack = [];

    /**
     * Constructor.
     *
     * @param string $query The query parameter name
     */
    public function __construct(protected string $query = 'df')
    {
    }

    /**
     * Parse ressource URL.
     *
     * @param string      $src  The source
     * @param null|string $type The type
     * @param null|string $id   The id
     * @param null|string $ext  The extension
     *
     * @return string The URL
     */
    public function url(string $src, ?string $type = null, ?string $id = null, ?string $ext = null): string
    {
        if (str_starts_with($src, 'http')) {
            return $src;
        }

        if ($ext) {
            $src = $ext . '/' . $src;
        }

        if ('var' == $type) {
            $src = 'var/' . $src;
        } elseif (!empty($type) && !empty($id)) {
            $src = implode('/', [$type, $id, $src]);
        }

        return App::core()->config()->get('admin_url') . (str_contains($src, '?') ? '' : '?') . $this->query . '=' . $src;
    }

    /**
     * Parse ressource css URL.
     *
     * @see     self::parse();
     *
     * @param string      $src     The source
     * @param null|string $type    The type
     * @param null|string $id      The id
     * @param null|string $version The version
     *
     * @return string The URL
     */
    public function css(string $src, ?string $type = null, ?string $id = null, ?string $version = null): string
    {
        return $this->parse($src, $type, $id, null, false, 'css', $version);
    }

    /**
     * Parse ressource js URL.
     *
     * @see     self::parse();
     *
     * @param string      $src     The source
     * @param null|string $type    The type
     * @param null|string $id      The id
     * @param null|string $version The version
     *
     * @return string The URL
     */
    public function js(string $src, ?string $type = null, ?string $id = null, ?string $version = null): string
    {
        return $this->parse($src, $type, $id, null, false, 'js', $version);
    }

    /**
     * Parse ressource load URL.
     *
     * @see     self::parse();
     *
     * @param string      $src     The source
     * @param null|string $type    The type
     * @param null|string $id      The id
     * @param null|string $option  The option
     * @param null|string $version The version
     *
     * @return string The URL
     */
    public function load(string $src, ?string $type = null, ?string $id = null, ?string $option = null, ?string $version = null): string
    {
        return $this->parse($src, $type, $id, $option, false, null, $version);
    }

    /**
     * Parse ressource preload URL.
     *
     * @see     self::parse();
     *
     * @param string      $src     The source
     * @param null|string $type    The type
     * @param null|string $id      The id
     * @param null|string $option  The option
     * @param null|string $version The version
     *
     * @return string The URL
     */
    public function preload(string $src, ?string $type = null, ?string $id = null, ?string $option = null, ?string $version = null): string
    {
        return $this->parse($src, $type, $id, $option, true, null, $version);
    }

    /**
     * Parse ressource URL.
     *
     * This keep track of (pre)load ressource to serve their URL HTML code once.
     *
     * @param string      $src     The source
     * @param null|string $type    The type
     * @param null|string $id      The id
     * @param null|string $option  The option
     * @param bool        $preload Preload
     * @param null|string $ext     The extension
     * @param null|string $version The version
     *
     * @return string The URL
     */
    private function parse(string $src, ?string $type = null, ?string $id = null, ?string $option = null, bool $preload = false, ?string $ext = null, ?string $version = null): string
    {
        $src_ext = Files::getExtension($src);
        if (!$ext) {
            $ext = $src_ext;
        }

        if (!in_array($ext, ['js', 'css'])) {
            return '';
        }

        $url = $this->url($src, $type, $id, $ext);
        if (array_key_exists($url, $this->stack) && $this->stack[$url] === $preload) {
            return '';
        }
        $this->stack[$url] = $preload;

        $url = Html::escapeHTML($url);

        $url .= '&amp;v=' . ($version ?? (!App::core()->production() ? md5(uniqid()) : App::core()->config()->get('core_version')));

        if ($preload) {
            return '<link rel="preload" href="' . $url . '" as="' . ($option ?: 'style') . '" />' . "\n";
        }
        if ('css' == $src_ext) {
            return '<link rel="stylesheet" href="' . $url . '" type="text/css" media="' . ($option ?: 'screen') . '" />' . "\n";
        }

        return '<script src="' . $url . '"></script>' . "\n";
    }

    /**
     * Serve a ressource.
     */
    public function serve(): void
    {
        if (empty($_GET[$this->query])) {
            return;
        }

        $src  = $_GET[$this->query];
        $dirs = [];

        // Check if it in Var path
        $var_src  = explode('/', $src);
        $var_path = App::core()->config()->get('var_dir');
        if (1 < count($var_src) && array_shift($var_src) == 'var' && !empty($var_path) && is_dir($var_path)) {
            $dirs[] = $var_path;
            $src    = implode('/', $var_src);
        }

        // Try to find module id and type
        // Admin url should be ?df=ModuleType/ModuleId/a_sub_folder/a_file.ext
        $module_src = explode('/', $src);
        if (empty($dirs) && 2 < count($module_src)) {
            $module_type = array_shift($module_src);
            $module_id   = array_shift($module_src);

            // Check module type
            $modules = new Modules(type: $module_type, no_load: true);
            // Chek if module path exists
            foreach ($modules->getPaths() as $modules_path) {
                if (is_dir(Path::implode($modules_path, $module_id))) {
                    $dirs[] = Path::implode($modules_path, $module_id, 'Admin', 'resources');
                    $dirs[] = Path::implode($modules_path, $module_id, 'Common', 'resources');
                    $dirs[] = Path::implode($modules_path, $module_id); // required for icons
                    $src    = implode('/', $module_src);

                    break;
                }
            }
            unset($modules);
        }

        // List other available file paths
        $dirs[] = Path::implodeSrc('Process', 'Admin', 'resources');
        $dirs[] = Path::implodeSrc('Core', 'resources', 'css');
        $dirs[] = Path::implodeSrc('Core', 'resources', 'js');

        // Search dirs
        Files::serveFile($src, $dirs, App::core()->config()->get('file_sever_type'));
    }

    /**
     * Get json HTML code.
     *
     * @param string $id   The id
     * @param mixed  $vars The vars
     */
    public function json(string $id, mixed $vars): string
    {
        return '<script type="application/json" id="' . Html::escapeHTML($id) . '-data">' . "\n" .
            json_encode($vars, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . "\n" . '</script>';
    }

    /**
     * Get HTML code to load common JS for admin pages.
     */
    public function common(): string
    {
        $nocheckadblocker = null;
        if (App::core()->user()->preference()) {
            $nocheckadblocker = App::core()->user()->preference()->get('interface')->get('nocheckadblocker');
        }

        $js = [
            'nonce' => App::core()->nonce()->get(),

            'img_plus_src' => '?df=images/expand.svg',
            'img_plus_txt' => '▶',
            'img_plus_alt' => __('uncover'),

            'img_minus_src' => '?df=images/hide.svg',
            'img_minus_txt' => '▼',
            'img_minus_alt' => __('hide'),

            'adblocker_check' => App::core()->config()->get('admin_adblocker_check') && true !== $nocheckadblocker,
        ];

        $js_msg = [
            'help'                                 => __('Need help?'),
            'new_window'                           => __('new window'),
            'help_hide'                            => __('Hide'),
            'to_select'                            => __('Select:'),
            'no_selection'                         => __('no selection'),
            'select_all'                           => __('select all'),
            'invert_sel'                           => __('Invert selection'),
            'website'                              => __('Web site:'),
            'email'                                => __('Email:'),
            'ip_address'                           => __('IP address:'),
            'error'                                => __('Error:'),
            'entry_created'                        => __('Entry has been successfully created.'),
            'edit_entry'                           => __('Edit entry'),
            'view_entry'                           => __('view entry'),
            'confirm_delete_posts'                 => __('Are you sure you want to delete selected entries (%s)?'),
            'confirm_delete_medias'                => __('Are you sure you want to delete selected medias (%d)?'),
            'confirm_delete_categories'            => __('Are you sure you want to delete selected categories (%s)?'),
            'confirm_delete_post'                  => __('Are you sure you want to delete this entry?'),
            'click_to_unlock'                      => __('Click here to unlock the field'),
            'confirm_spam_delete'                  => __('Are you sure you want to delete all spams?'),
            'confirm_delete_comments'              => __('Are you sure you want to delete selected comments (%s)?'),
            'confirm_delete_comment'               => __('Are you sure you want to delete this comment?'),
            'cannot_delete_users'                  => __('Users with posts cannot be deleted.'),
            'confirm_delete_user'                  => __('Are you sure you want to delete selected users (%s)?'),
            'confirm_delete_blog'                  => __('Are you sure you want to delete selected blogs (%s)?'),
            'confirm_delete_category'              => __('Are you sure you want to delete category "%s"?'),
            'confirm_reorder_categories'           => __('Are you sure you want to reorder all categories?'),
            'confirm_delete_media'                 => __('Are you sure you want to remove media "%s"?'),
            'confirm_delete_directory'             => __('Are you sure you want to remove directory "%s"?'),
            'confirm_extract_current'              => __('Are you sure you want to extract archive in current directory?'),
            'confirm_remove_attachment'            => __('Are you sure you want to remove attachment "%s"?'),
            'confirm_delete_lang'                  => __('Are you sure you want to delete "%s" language?'),
            'confirm_delete_plugin'                => __('Are you sure you want to delete "%s" plugin?'),
            'confirm_delete_plugins'               => __('Are you sure you want to delete selected plugins?'),
            'use_this_theme'                       => __('Use this theme'),
            'remove_this_theme'                    => __('Remove this theme'),
            'confirm_delete_theme'                 => __('Are you sure you want to delete "%s" theme?'),
            'confirm_delete_themes'                => __('Are you sure you want to delete selected themes?'),
            'confirm_delete_backup'                => __('Are you sure you want to delete this backup?'),
            'confirm_revert_backup'                => __('Are you sure you want to revert to this backup?'),
            'zip_file_content'                     => __('Zip file content'),
            'xhtml_validator'                      => __('XHTML markup validator'),
            'xhtml_valid'                          => __('XHTML content is valid.'),
            'xhtml_not_valid'                      => __('There are XHTML markup errors.'),
            'warning_validate_no_save_content'     => __('Attention: an audit of a content not yet registered.'),
            'confirm_change_post_format'           => __('You have unsaved changes. Switch post format will loose these changes. Proceed anyway?'),
            'confirm_change_post_format_noconvert' => __('Warning: post format change will not convert existing content. You will need to apply new format by yourself. Proceed anyway?'),
            'load_enhanced_uploader'               => __('Loading enhanced uploader, please wait.'),

            'module_author'  => __('Author:'),
            'module_details' => __('Details'),
            'module_support' => __('Support'),
            'module_help'    => __('Help:'),
            'module_section' => __('Section:'),
            'module_tags'    => __('Tags:'),

            'close_notice' => __('Hide this notice'),

            'show_password' => __('Show password'),
            'hide_password' => __('Hide password'),

            'set_today' => __('Reset to now'),

            'adblocker' => __('An ad blocker has been detected on this Dotclear dashboard (Ghostery, Adblock plus, uBlock origin, …) and it may interfere with some features. In this case you should disable it. Note that this detection may be disabled in your preferences.'),
        ];

        return
        $this->load('prepend.js') .
        $this->load('jquery/jquery.js') .
        (
            !App::core()->production() ?
            $this->json('dotclear_jquery', [
                'mute' => false === App::core()->blog()?->settings()->get('system')->get('jquery_migrate_mute'),
            ]) .
            $this->load('jquery-mute.js') .
            $this->load('jquery/jquery-migrate.js') :
            ''
        ) .

        $this->json('dotclear', $js) .
        $this->json('dotclear_msg', $js_msg) .

        $this->load('common.js') .
        $this->load('ads.js') .
        $this->load('services.js') .
        $this->load('prelude.js');
    }

    /**
     * Get HTML code to load toggles JS.
     */
    public function toggles(): string
    {
        $js = [];
        if (App::core()->user()->preference()) {
            $unfolded_sections = explode(',', (string) App::core()->user()->preference()->get('toggles')->get('unfolded_sections'));
            foreach ($unfolded_sections as $k => &$v) {
                if ('' !== $v) {
                    $js[$unfolded_sections[$k]] = true;
                }
            }
        }

        return
        $this->json('dotclear_toggles', $js) .
        $this->load('toggles.js');
    }

    /**
     * Get HTML to load Upload JS utility.
     *
     * @param array       $params   The parameters
     * @param null|string $base_url The base url
     */
    public function upload(array $params = [], ?string $base_url = null): string
    {
        if (!$base_url) {
            $base_url = Path::clean(dirname(preg_replace('/(\?.*$)?/', '', $_SERVER['REQUEST_URI']))) . '/';
        }

        $params = array_merge($params, [
            'sess_id=' . session_id(),
            'sess_uid=' . $_SESSION['sess_browser_uid'],
            'xd_check=' . App::core()->nonce()->get(),
        ]);

        $js_msg = [
            'enhanced_uploader_activate' => __('Temporarily activate enhanced uploader'),
            'enhanced_uploader_disable'  => __('Temporarily disable enhanced uploader'),
        ];
        $js = [
            'msg' => [
                'limit_exceeded'             => __('Limit exceeded.'),
                'size_limit_exceeded'        => __('File size exceeds allowed limit.'),
                'canceled'                   => __('Canceled.'),
                'http_error'                 => __('HTTP Error:'),
                'error'                      => __('Error:'),
                'choose_file'                => __('Choose file'),
                'choose_files'               => __('Choose files'),
                'cancel'                     => __('Cancel'),
                'clean'                      => __('Clean'),
                'upload'                     => __('Upload'),
                'send'                       => __('Send'),
                'file_successfully_uploaded' => __('File successfully uploaded.'),
                'no_file_in_queue'           => __('No file in queue.'),
                'file_in_queue'              => __('1 file in queue.'),
                'files_in_queue'             => __('%d files in queue.'),
                'queue_error'                => __('Queue error:'),
            ],
            'base_url' => $base_url,
        ];

        return
        $this->json('file_upload', $js) .
        $this->json('file_upload_msg', $js_msg) .
        $this->load('file-upload.js') .
        $this->load('jquery/jquery-ui.custom.js') .
        $this->load('jsUpload/tmpl.js') .
        $this->load('jsUpload/template-upload.js') .
        $this->load('jsUpload/template-download.js') .
        $this->load('jsUpload/load-image.js') .
        $this->load('jsUpload/jquery.iframe-transport.js') .
        $this->load('jsUpload/jquery.fileupload.js') .
        $this->load('jsUpload/jquery.fileupload-process.js') .
        $this->load('jsUpload/jquery.fileupload-resize.js') .
        $this->load('jsUpload/jquery.fileupload-ui.js');
    }

    /**
     * Get HTML code to load Magnific popup JS.
     */
    public function modal(): string
    {
        return $this->load('jquery/jquery.magnific-popup.js');
    }

    /**
     * Get HTML code to load ConfirmClose JS.
     *
     * @param string ...$args The arguments
     */
    public function confirmClose(string ...$args): string
    {
        $js = [
            'prompt' => __('You have unsaved changes.'),
            'forms'  => $args,
        ];

        return
        $this->json('confirm_close', $js) .
        $this->load('confirm-close.js');
    }

    /**
     * Get HTML code to load page tabs JS.
     *
     * @param null|string $default The default
     */
    public function pageTabs(?string $default = null): string
    {
        $js = [
            'default' => $default,
        ];

        return
        $this->json('page_tabs', $js) .
        $this->load('jquery/jquery.pageTabs.js') .
        $this->load('page-tabs.js');
    }

    /**
     * Get HTML code to load meta editor.
     */
    public function metaEditor(): string
    {
        return $this->load('meta-editor.js');
    }

    /**
     * Get HTML code to load Codemirror.
     *
     * @param string $theme The theme
     * @param bool   $multi Is multiplex?
     * @param array  $modes The modes
     */
    public function loadCodeMirror($theme = '', $multi = true, $modes = ['css', 'htmlmixed', 'javascript', 'php', 'xml', 'clike']): string
    {
        $ret = $this->js('codemirror/lib/codemirror.css') .
        $this->load('codemirror/lib/codemirror.js');
        if ($multi) {
            $ret .= $this->load('codemirror/addon/mode/multiplex.js');
        }
        foreach ($modes as $mode) {
            $ret .= $this->load('codemirror/mode/' . $mode . '/' . $mode . '.js');
        }
        $ret .= $this->load('codemirror/addon/edit/closebrackets.js') .
        $this->load('codemirror/addon/edit/matchbrackets.js') .
        $this->js('codemirror/addon/display/fullscreen.css') .
        $this->load('codemirror/addon/display/fullscreen.js');
        if ('' != $theme && 'default' !== $theme) {
            $ret .= $this->js('codemirror/theme/' . $theme . '.css');
        }

        return $ret;
    }

    /**
     * Get HTML code to run Codemirror.
     *
     * @param mixed  $name  The HTML name attribute
     * @param mixed  $id    The HTML id attribute
     * @param mixed  $mode  The Codemirror mode
     * @param string $theme The theme
     */
    public function runCodeMirror($name, $id = null, $mode = null, $theme = ''): string
    {
        if (is_array($name)) {
            $js = $name;
        } else {
            $js = [[
                'name'  => $name,
                'id'    => $id,
                'mode'  => $mode,
                'theme' => $theme ?: 'default',
            ]];
        }

        return
            $this->json('codemirror', $js) .
            $this->load('codemirror.js');
    }

    /**
     * Gets the codemirror themes list.
     *
     * @return array the code mirror themes
     */
    public function getCodeMirrorThemes(): array
    {
        $themes      = [];
        $themes_root = Path::implodeSrc('Process', 'Admin', 'resources', 'js', 'codemirror', 'theme');
        if (is_dir($themes_root) && is_readable($themes_root)) {
            if (false !== ($d = @dir($themes_root))) {
                while (false !== ($entry = $d->read())) {
                    if (!in_array($entry, ['.', '..']) && '.' != substr($entry, 0, 1) && is_readable($themes_root . '/' . $entry)) {
                        $themes[] = substr($entry, 0, -4); // remove .css extension
                    }
                }
                sort($themes);
            }
        }

        return $themes;
    }
}
