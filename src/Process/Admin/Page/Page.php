<?php
/**
 * @class Dotclear\Process\Admin\Page\Page
 * @brief Dotclear admin page helper class
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Page;

use ArrayObject;

use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Statistic;

abstract class Page
{
    /** @var string|null    Page type */
    private $page_type = null;

    /** @var string         Page title */
    private $page_title = '';

    /** @var string         Page head */
    private $page_head = '';

    /** @var string         Page content */
    private $page_content = '';

    /** @var array          Help blocks names */
    private $page_help = [];

    /** @var array          Page breadcrumb (brut)) */
    private $page_breadcrumb = ['elements' => null, 'options' => []];

    /** @var bool           Load once xframe */
    private $page_xframe_loaded = false;

    /** @var string         Handler name that calls page */
    protected $handler;

    /** @var Action         Action instance */
    protected $action;

    /** @var Filter         Filter instance */
    protected $filter;

    /** @var Inventory      Inventory instance */
    protected $inventory;

    /** @var array          Misc options for page content */
    protected $options = [];

    public function __construct(string $handler = 'admin.home')
    {
        $this->handler = $handler;

        # Check user permissions
        $this->pagePermissions();
    }

    /**
     * Check if current page is home page
     *
     * @return  bool    Is home page
     */
    final public function isHome(): bool
    {
        return $this->handler == 'admin.home';
    }

    /**
     * Check if current page is authentication page
     *
     * @return  bool    Is auth page
     */
    final public function isAuth(): bool
    {
        return $this->handler == 'admin.auth';
    }

    /**
     * Process page display
     *
     * Split process into readable methods
     */
    final public function pageProcess(): void
    {
        $this->pageInstances();
        $action = $this->pagePrepend();

        if ($action === null) {
            return;
        }

        $this->pageBegin();
        $this->pageBreadcrumb();
        $this->pageNotices();
        $this->pageContent();
        $this->pageHelp();
        $this->pageEnd();

        if ($action !== null) {
            exit;
        }
    }

    /// @name Page internal methods
    //@{
    /**
     * Check user permissions to load this page
     */
    private function pagePermissions(): void
    {
        $permissions = $this->getPermissions();

        # No permissions required
        if ($permissions === false) {
            return;
        }

        # Super Admin
        if (dotclear()->user()->isSuperAdmin()) {
            return;
        }

        # Has required permissions
        if (is_string($permissions) && dotclear()->blog() && dotclear()->user()->check($this->getPermissions(), dotclear()->blog()->id)) {
            return;
        }

        # Check if dashboard is not the current page and if it is granted for the user
        if (!$this->isHome && dotclear()->blog() && dotclear()->user()->check('usage,contentadmin', dotclear()->blog()->id)) {
            # Go back to the dashboard
            dotclear()->adminurl()->redirect('admin.home');
        }

        # Not enought permissions
        if (session_id()) {
            dotclear()->session()->destroy();
        }
        # Go to auth page
        dotclear()->adminurl()->redirect('admin.auth');
    }

    /**
     * Load into page usefull instance
     *
     * Class type is verified by abstract class type hint.
     */
    private function pageInstances(): void
    {
        try {
            # Load and process page Action
            if (($action_class = $this->getActionInstance()) !== null) {
                $this->action = $action_class;
                $this->action->pageProcess();
            }

            # Load list Filter
            if (($filter_class = $this->getFilterInstance()) !== null) {
                $this->filter = $filter_class;
            }

            # Load list Inventory
            if (($inventory_class = $this->GetInventoryInstance()) !== null) {
                $this->inventory = $inventory_class;
            }
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }
    }

    private function pagePrepend(): ?bool
    {
        return $this->getPagePrepend();
    }

    private function pageBegin(): void
    {
        switch ($this->page_type) {
            case null:
            case 'full':
                $this->pageOpen();
                break;

            case 'plugin':
                $this->pageOpenPlugin();
                break;

            case 'popup':
                $this->pageOpenPopup();
                break;

            case 'standalone':
                break;

            default:
                $this->getPageBegin();
                break;
        }
    }

    /**
     * The top of a popup.
     */
    public function pageOpen(): void
    {
        $js   = [];

        # List of user's blogs
        if (dotclear()->user()->getBlogCount() == 1 || dotclear()->user()->getBlogCount() > 20) {
            $blog_box = '<p>' . __('Blog:') . ' <strong title="' . Html::escapeHTML(dotclear()->blog()->url) . '">' .
            Html::escapeHTML(dotclear()->blog()->name) . '</strong>';

            if (dotclear()->user()->getBlogCount() > 20) {
                $blog_box .= ' - <a href="' . dotclear()->adminurl()->get('admin.blogs') . '">' . __('Change blog') . '</a>';
            }
            $blog_box .= '</p>';
        } else {
            $rs_blogs = dotclear()->blogs()->getBlogs(['order' => 'LOWER(blog_name)', 'limit' => 20]);
            $blogs    = [];
            while ($rs_blogs->fetch()) {
                $blogs[Html::escapeHTML($rs_blogs->blog_name . ' - ' . $rs_blogs->blog_url)] = $rs_blogs->blog_id;
            }
            $blog_box = '<p><label for="switchblog" class="classic">' . __('Blogs:') . '</label> ' .
            dotclear()->nonce()->form() . Form::combo('switchblog', $blogs, dotclear()->blog()->id) .
            Form::hidden(['redir'], $_SERVER['REQUEST_URI']) .
            '<input type="submit" value="' . __('ok') . '" class="hidden-if-js" /></p>';
        }

        $safe_mode = isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];

        # Display
        $headers = new ArrayObject([]);

        # Content-Type
        $headers['content-type'] = 'Content-Type: text/html; charset=UTF-8';

        # Referrer Policy for admin pages
        $headers['referrer'] = 'Referrer-Policy: strict-origin';

        # Prevents Clickjacking as far as possible
        if (isset($this->options['x-frame-allow'])) {
            $this->setXFrameOptions($headers, $this->options['x-frame-allow']);
        } else {
            $this->setXFrameOptions($headers);
        }

        # Content-Security-Policy (only if safe mode if not active, it may help)
        if (!$safe_mode && dotclear()->blog()->settings()->system->csp_admin_on) {
            // Get directives from settings if exist, else set defaults
            $csp = new ArrayObject([]);

            // SQlite Clearbricks driver does not allow using single quote at beginning or end of a field value
                                                                                // so we have to use neutral values (localhost and 127.0.0.1) for some CSP directives
            $csp_prefix = dotclear()->con()->syntax() == 'sqlite' ? 'localhost ' : ''; // Hack for SQlite Clearbricks syntax
            $csp_suffix = dotclear()->con()->syntax() == 'sqlite' ? ' 127.0.0.1' : ''; // Hack for SQlite Clearbricks syntax

            $csp['default-src'] = dotclear()->blog()->settings()->system->csp_admin_default ?:
            $csp_prefix . "'self'" . $csp_suffix;
            $csp['script-src'] = dotclear()->blog()->settings()->system->csp_admin_script ?:
            $csp_prefix . "'self' 'unsafe-eval'" . $csp_suffix;
            $csp['style-src'] = dotclear()->blog()->settings()->system->csp_admin_style ?:
            $csp_prefix . "'self' 'unsafe-inline'" . $csp_suffix;
            $csp['img-src'] = dotclear()->blog()->settings()->system->csp_admin_img ?:
            $csp_prefix . "'self' data: https://media.dotaddict.org blob:";

            # Cope with blog post preview (via public URL in iframe)
            if (!is_null(dotclear()->blog()->host)) {
                $csp['default-src'] .= ' ' . parse_url(dotclear()->blog()->host, PHP_URL_HOST);
                $csp['script-src']  .= ' ' . parse_url(dotclear()->blog()->host, PHP_URL_HOST);
                $csp['style-src']   .= ' ' . parse_url(dotclear()->blog()->host, PHP_URL_HOST);
            }
            # Cope with media display in media manager (via public URL)
            if (dotclear()->media()) {
                $csp['img-src'] .= ' ' . parse_url(dotclear()->media()->root_url, PHP_URL_HOST);
            } elseif (!is_null(dotclear()->blog()->host)) {
                // Let's try with the blog URL
                $csp['img-src'] .= ' ' . parse_url(dotclear()->blog()->host, PHP_URL_HOST);
            }
            # Allow everything in iframe (used by editors to preview public content)
            $csp['frame-src'] = '*';

            # --BEHAVIOR-- adminPageHTTPHeaderCSP, ArrayObject
            dotclear()->behavior()->call('adminPageHTTPHeaderCSP', $csp);

            // Construct CSP header
            $directives = [];
            foreach ($csp as $key => $value) {
                if ($value) {
                    $directives[] = $key . ' ' . $value;
                }
            }
            if (count($directives)) {
                $directives[]   = 'report-uri ' . dotclear()->config()->admin_url . '?handler=admin.cspreport';
                $report_only    = (dotclear()->blog()->settings()->system->csp_admin_report_only) ? '-Report-Only' : '';
                $headers['csp'] = 'Content-Security-Policy' . $report_only . ': ' . implode(' ; ', $directives);
            }
        }

        # --BEHAVIOR-- adminPageHTTPHeaders, ArrayObject
        dotclear()->behavior()->call('adminPageHTTPHeaders', $headers);

        foreach ($headers as $key => $value) {
            header($value);
        }

        $data_theme = dotclear()->user()->preference()->interface->theme;

        echo
        '<!DOCTYPE html>' .
        '<html lang="' . dotclear()->user()->getInfo('user_lang') . '" data-theme="' . $data_theme . '">' . "\n" .
        "<head>\n" .
        '  <meta charset="UTF-8" />' . "\n" .
        '  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />' . "\n" .
        '  <meta name="GOOGLEBOT" content="NOSNIPPET" />' . "\n" .
        '  <meta name="viewport" content="width=device-width, initial-scale=1.0" />' . "\n" .
        '  <title>' . $this->page_title . ' - ' . Html::escapeHTML(dotclear()->blog()->name) . ' - ' . Html::escapeHTML(dotclear()->config()->vendor_name) . ' - ' . dotclear()->config()->core_version . '</title>' . "\n";

        echo dotclear()->resource()->preload('default.css') . dotclear()->resource()->load('default.css');

        if (L10n::getLanguageTextDirection(dotclear()->_lang) == 'rtl') {
            echo dotclear()->resource()->load('default-rtl.css');
        }

        if (!dotclear()->user()->preference()->interface->hide_std_favicon) {
            echo
                '<link rel="icon" type="image/png" href="?df=images/favicon96-login.png" />' . "\n" .
                '<link rel="shortcut icon" href="?df=images/favicon.ico" type="image/x-icon" />' . "\n";
        }
        if (dotclear()->user()->preference()->interface->htmlfontsize) {
            $js['htmlFontSize'] = dotclear()->user()->preference()->interface->htmlfontsize;
        }
        $js['hideMoreInfo']   = (bool) dotclear()->user()->preference()->interface->hidemoreinfo;
        $js['showAjaxLoader'] = (bool) dotclear()->user()->preference()->interface->showajaxloader;

        $js['noDragDrop'] = (bool) dotclear()->user()->preference()->accessibility->nodragdrop;

        $js['debug'] = !dotclear()->production();

        $js['showIp'] = dotclear()->blog() && dotclear()->blog()->id ? dotclear()->user()->check('contentadmin', dotclear()->blog()->id) : false;

        // Set some JSON data
        echo
        dotclear()->resource()->json('dotclear_init', $js) .
        dotclear()->resource()->common() .
        dotclear()->resource()->toggles() .
        $this->page_head;

        # --BEHAVIOR-- adminPageHTMLHead, string, string
        dotclear()->behavior()->call('adminPageHTMLHead', $this->handler, $this->page_type);

        echo
        "</head>\n" .
        '<body id="dotclear-admin" class="no-js' .
        ($safe_mode ? ' safe-mode' : '') .
        (!dotclear()->production() ?
            ' debug-mode' :
            '') .
        '">' . "\n" .

        '<ul id="prelude">' .
        '<li><a href="#content">' . __('Go to the content') . '</a></li>' .
        '<li><a href="#main-menu">' . __('Go to the menu') . '</a></li>' .
        '<li><a href="#help">' . __('Go to help') . '</a></li>' .
        '</ul>' . "\n" .
        '<header id="header" role="banner">' .
        '<h1><a href="' . dotclear()->adminurl()->get('admin.home') . '"><span class="hidden">' . dotclear()->config()->vendor_name . '</span></a></h1>' . "\n";

        echo
        '<form action="' . dotclear()->adminurl()->get('admin.home') . '" method="post" id="top-info-blog">' .
        $blog_box .
        '<p><a href="' . dotclear()->blog()->url . '" class="outgoing" title="' . __('Go to site') .
        '">' . __('Go to site') . '<img src="?df=images/outgoing-link.svg" alt="" /></a>' .
        '</p></form>' .
        '<ul id="top-info-user">' .
        '<li><a class="' . (preg_match('"' . preg_quote(dotclear()->adminurl()->get('admin.home')) . '$"', $_SERVER['REQUEST_URI']) ? ' active' : '') . '" href="' . dotclear()->adminurl()->get('admin.home') . '">' . __('My dashboard') . '</a></li>' .
        '<li><a class="smallscreen' . (preg_match('"' . preg_quote(dotclear()->adminurl()->get('admin.user.pref')) . '(\?.*)?$"', $_SERVER['REQUEST_URI']) ? ' active' : '') .
        '" href="' . dotclear()->adminurl()->get('admin.user.pref') . '">' . __('My preferences') . '</a></li>' .
        '<li><a href="' . dotclear()->adminurl()->get('admin.home', ['logout' => 1]) . '" class="logout"><span class="nomobile">' . sprintf(__('Logout %s'), dotclear()->user()->userID()) .
            '</span><img src="?df=images/logout.svg" alt="" /></a></li>' .
            '</ul>' .
            '</header>'; // end header

        echo
        '<div id="wrapper" class="clearfix">' . "\n" .
        '<div class="hidden-if-no-js collapser-box"><button type="button" id="collapser" class="void-btn">' .
        '<img class="collapse-mm visually-hidden" src="?df=images/collapser-hide.png" alt="' . __('Hide main menu') . '" />' .
        '<img class="expand-mm visually-hidden" src="?df=images/collapser-show.png" alt="' . __('Show main menu') . '" />' .
            '</button></div>' .
            '<main id="main" role="main">' . "\n" .
            '<div id="content" class="clearfix">' . "\n";

        # Safe mode
        if ($safe_mode) {
            echo
            '<div class="warning" role="alert"><h3>' . __('Safe mode') . '</h3>' .
            '<p>' . __('You are in safe mode. All plugins have been temporarily disabled. Remind to log out then log in again normally to get back all functionalities') . '</p>' .
                '</div>';
        }
    }

    private function pageOpenPlugin(): void
    {
        echo
            '<html><head><title>' . $this->page_title . '</title>' .
            $this->page_head .
            '</script></head><body>';
    }

    /**
     * The top of a popup.
     */
    public function pageOpenPopup(): void
    {
        $js   = [];

        $safe_mode = isset($_SESSION['sess_safe_mode']) && $_SESSION['sess_safe_mode'];

        # Display
        header('Content-Type: text/html; charset=UTF-8');

        # Referrer Policy for admin pages
        header('Referrer-Policy: strict-origin');

        # Prevents Clickjacking as far as possible
        header('X-Frame-Options: SAMEORIGIN'); // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+

        $data_theme = dotclear()->user()->preference()->interface->theme;

        echo
        '<!DOCTYPE html>' .
        '<html lang="' . dotclear()->user()->getInfo('user_lang') . '" data-theme="' . $data_theme . '">' . "\n" .
        "<head>\n" .
        '  <meta charset="UTF-8" />' . "\n" .
        '  <meta name="viewport" content="width=device-width, initial-scale=1.0" />' . "\n" .
        '  <title>' . $this->page_title . ' - ' . Html::escapeHTML(dotclear()->blog()->name) . ' - ' . Html::escapeHTML(dotclear()->config()->vendor_name) . ' - ' . dotclear()->config()->core_version . '</title>' . "\n" .
            '  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />' . "\n" .
            '  <meta name="GOOGLEBOT" content="NOSNIPPET" />' . "\n";

        echo dotclear()->resource()->preload('default.css') . dotclear()->resource()->load('default.css');

        if (L10n::getLanguageTextDirection(dotclear()->_lang) == 'rtl') {
            echo dotclear()->resource()->load('default-rtl.css');
        }

        if (dotclear()->user()->preference()->interface->htmlfontsize) {
            $js['htmlFontSize'] = dotclear()->user()->preference()->interface->htmlfontsize;
        }
        $js['hideMoreInfo']   = (bool) dotclear()->user()->preference()->interface->hidemoreinfo;
        $js['showAjaxLoader'] = (bool) dotclear()->user()->preference()->interface->showajaxloader;

        $js['noDragDrop'] = (bool) dotclear()->user()->preference()->accessibility->nodragdrop;

        $js['debug'] = !dotclear()->production();

        // Set JSON data
        echo
        dotclear()->resource()->json('dotclear_init', $js) .
        dotclear()->resource()->common() .
        dotclear()->resource()->toggles() .
        $this->page_head;

        # --BEHAVIOR-- adminPageHTMLHead, string, string
        dotclear()->behavior()->call('adminPageHTMLHead', $this->handler, $this->page_type);

        echo
            "</head>\n" .
            '<body id="dotclear-admin" class="popup' .
            ($safe_mode ? ' safe-mode' : '') .
            (!dotclear()->production() ?
                ' debug-mode' :
                '') .
            '">' . "\n" .

            '<h1>' . dotclear()->config()->vendor_name . '</h1>' . "\n";

        echo
            '<div id="wrapper">' . "\n" .
            '<main id="main" role="main">' . "\n" .
            '<div id="content">' . "\n";
    }

    private function pageBreadcrumb(): void
    {
        $elements = $this->page_breadcrumb['elements'];
        $options  = $this->page_breadcrumb['options'];

        if ($elements === null) {
            return;
        }

        $with_home_link = $options['home_link'] ?? true;
        $hl             = $options['hl']        ?? true;
        $hl_pos         = $options['hl_pos']    ?? -1;
        // First item of array elements should be blog's name, System or Plugins
        $res = '<h2>' . ($with_home_link ?
            '<a class="go_home" href="' . dotclear()->adminurl()->get('admin.home') . '">' .
            '<img class="go_home light-only" src="?df=css/dashboard.svg" alt="' . __('Go to dashboard') . '" />' .
            '<img class="go_home dark-only" src="?df=css/dashboard-dark.svg" alt="' . __('Go to dashboard') . '" />' .
            '</a>' :
            '<img class="go_home light-only" src="?df=css/dashboard-alt.svg" alt="" />' .
            '<img class="go_home dark-only" src="?df=css/dashboard-alt-dark.svg" alt="" />');
        $index = 0;
        if ($hl_pos < 0) {
            $hl_pos = count($elements) + $hl_pos;
        }
        foreach ($elements as $element => $url) {
            if ($hl && $index == $hl_pos) {
                $element = sprintf('<span class="page-title">%s</span>', $element);
            }
            $res .= ($with_home_link ? ($index == 1 ? ' : ' : ' &rsaquo; ') : ($index == 0 ? ' ' : ' &rsaquo; ')) .
                ($url ? '<a href="' . $url . '">' : '') . $element . ($url ? '</a>' : '');
            $index++;
        }
        $res .= '</h2>';

        echo $res;
    }

    private function pageNotices(): void
    {
        echo dotclear()->notice()->getNotices();
    }

    private function pageContent(): void
    {
        $this->getPageContent();
    }

    /**
     * Display Help block
     */
    private function pageHelp(): void
    {
        if (!dotclear()->user()->preference()) {
            return;
        }
        if (dotclear()->user()->preference()->interface->hidehelpbutton) {
            return;
        }

        $args = new ArrayObject($this->page_help);

        # --BEHAVIOR-- adminPageHelpBlock, ArrayObject
        dotclear()->behavior()->call('adminPageHelpBlock', $args);

        if (!count($args)) {
            return;
        };

        if (empty(dotclear()->resources['help'])) {
            return;
        }

        $content = '';
        foreach ($args as $v) {
            if (is_object($v) && isset($v->content)) {
                $content .= $v->content;

                continue;
            }

            if (!isset(dotclear()->resources['help'][$v])) {
                continue;
            }
            $f = dotclear()->resources['help'][$v];
            if (!file_exists($f) || !is_readable($f)) {
                continue;
            }

            $fc = file_get_contents($f);
            if (preg_match('|<body[^>]*?>(.*?)</body>|ms', $fc, $matches)) {
                $content .= $matches[1];
            } else {
                $content .= $fc;
            }
        }

        if (trim($content) == '') {
            return;
        }

        // Set contextual help global flag
        dotclear()->resources['ctxhelp'] = true;

        echo
        '<div id="help"><hr /><div class="help-content clear"><h3>' . __('Help about this page') . '</h3>' .
        $content .
        '</div>' .
        '<div id="helplink"><hr />' .
        '<p>' .
        sprintf(__('See also %s'), sprintf('<a href="' . dotclear()->adminurl()->get('admin.help') . '">%s</a>', __('the global help'))) .
            '.</p>' .
            '</div></div>';
    }

    private function pageEnd(): void
    {
        switch ($this->page_type) {
            case null:
            case 'full':
            case 'plugin':
                $this->pageClose();
                break;

            case 'popup':
                $this->pageClosePopup();
                break;

            case 'standalone':
                break;

            default:
                $this->getPageEnd();
                break;
        }
    }

    private function pageClose(): void
    {
        if (!dotclear()->resources['ctxhelp'] && !dotclear()->user()->preference()->interface->hidehelpbutton) {
            echo sprintf(
                '<p id="help-button"><a href="%1$s" class="outgoing" title="%2$s">%2$s</a></p>',
                dotclear()->adminurl()->get('admin.help'),
                 __('Global help')
            );
        }

        echo
        "</div>\n" .  // End of #content
        "</main>\n" . // End of #main

        '<nav id="main-menu" role="navigation">' . "\n" .

        '<form id="search-menu" action="' . dotclear()->adminurl()->get('admin.search') . '" method="get" role="search">' .
        '<p><label for="qx" class="hidden">' . __('Search:') . ' </label>' . Form::field('qx', 30, 255, '') .
        '<input type="submit" value="' . __('OK') . '" /></p>' .
            '</form>';

        foreach (dotclear()->summary() as $k => $v) {
            echo dotclear()->summary()[$k]->draw();
        }

        $text = sprintf(__('Thank you for using %s.'), 'Dotclear ' . dotclear()->config()->core_version);

        # --BEHAVIOR-- adminPageFooter, string
        $textAlt = dotclear()->behavior()->call('adminPageFooter', $text);
        if ($textAlt != '') {
            $text = $textAlt;
        }
        $text = Html::escapeHTML($text);

        echo
        '</nav>' . "\n" . // End of #main-menu
        "</div>\n";       // End of #wrapper

        echo '<p id="gototop"><a href="#wrapper">' . __('Page top') . '</a></p>' . "\n";

        $figure = <<<EOT

                (╯°□°)╯︵ ┻━┻

            EOT;

        echo
            '<footer id="footer" role="contentinfo">' .
            '<a href="https://dotclear.org/" title="' . $text . '">' .
            '<img src="?df=css/dc_logos/w-dotclear90.png" alt="' . $text . '" /></a></footer>' . "\n" .
            '<!-- ' . "\n" .
            $figure .
            ' -->' . "\n";

        if (!dotclear()->production()) {
            echo $this->pageDebugInfo();
        }

        echo
            '</body></html>';
    }

    /**
     * Get HTML code of debug information
     *
     * @return     string
     */
    private function pageDebugInfo(): string
    {
        $global_vars = implode(', ', array_keys($GLOBALS));

        $res = '<div id="debug"><div>' .
        '<p>memory usage: ' . memory_get_usage() . ' (' . Files::size(memory_get_usage()) . ')</p>';

        if (function_exists('xdebug_get_profiler_filename')) {
            $res .= '<p>Elapsed time: ' . xdebug_time_index() . ' seconds</p>';

            $prof_file = xdebug_get_profiler_filename();
            if ($prof_file) {
                $res .= '<p>Profiler file : ' . xdebug_get_profiler_filename() . '</p>';
            } else {
                $prof_url = Http::getSelfURI();
                $prof_url .= str_contains($prof_url, '?') ? '&' : '?';
                $prof_url .= 'XDEBUG_PROFILE';
                $res      .= '<p><a href="' . Html::escapeURL($prof_url) . '">Trigger profiler</a></p>';
            }

            /* xdebug configuration:
        zend_extension = /.../xdebug.so
        xdebug.auto_trace = On
        xdebug.trace_format = 0
        xdebug.trace_options = 1
        xdebug.show_mem_delta = On
        xdebug.profiler_enable = 0
        xdebug.profiler_enable_trigger = 1
        xdebug.profiler_output_dir = /tmp
        xdebug.profiler_append = 0
        xdebug.profiler_output_name = timestamp
         */
        }
        $res.= '<p>Core elapsed time: ' . Statistic::time() . ' | Core consumed memory: ' . Statistic::memory() . '</p>';

        $loaded_files = dotclear()->autoload()->getLoadedFiles();
        $res .= '<p>Autoloader provided files : ' . count($loaded_files) . ' (' . dotclear()->autoload()->getRequestsCount() . ' requests)</p>';
        //$res .= '<ul><li>' . implode('</li><li>', $loaded_files) . '</li></lu>';

        $res .= '<p>Global vars: ' . $global_vars . '</p>' .
            '</div></div>';

        return $res;
    }

    private function pageClosePopup(): void
    {
        echo implode("\n", [
            '</div>',  // End of #content
            '</main>', // End of #main
            '</div>',  // End of #wrapper

            '<p id="gototop"><a href="#wrapper">' . __('Page top') . '</a></p>',

            '<footer id="footer" role="contentinfo"><p>&nbsp;</p></footer>',
            '</body></html>'
        ]);
    }

    /**
     * Sets the x frame options.
     *
     * @param      array|ArrayObject    $headers  The headers
     * @param      mixed                $origin   The origin
     */
    public function setXFrameOptions(array|ArrayObject $headers, ?string $origin = null): void
    {
        if ($this->page_xframe_loaded) {
            return;
        }

        if ($origin !== null) {
            $url                        = parse_url($origin);
            $headers['x-frame-options'] = sprintf('X-Frame-Options: %s', is_array($url) && isset($url['host']) ?
                ('ALLOW-FROM ' . (isset($url['scheme']) ? $url['scheme'] . ':' : '') . '//' . $url['host']) :
                'SAMEORIGIN');
        } else {
            $headers['x-frame-options'] = 'X-Frame-Options: SAMEORIGIN'; // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+
        }
        $this->page_xframe_loaded = true;
    }
    //@}

    /// @name Page child class methods
    //@{
    /**
     * Set page type
     *
     * This must be set before page opening
     *
     * type can be :
     * - null or 'full' for standard page
     * - 'popup',
     * - ...
     *
     * If not default value, this should be set before page opening.
     *
     * @param   string|null     $page_type  The page type
     */
    final public function setPageType(?string $page_type = null): Page
    {
        $this->page_type = is_string($page_type) ? $page_type : 'full';

        return $this;
    }

    /**
     * Set page title
     *
     * This must be set before page opening
     *
     * @param   string|null  $page_title     The page title
     */
    final public function setPageTitle(?string $page_title): Page
    {
        $this->page_title = is_string($page_title) ? $page_title : '';

        return $this;
    }

    /**
     * Set page HTML head content
     *
     * This must be set before page opening
     *
     * @param   string|null     $page_head  The HTML code for head
     */
    final public function setPageHead(?string $page_head): Page
    {
        if (is_string($page_head)) {
            $this->page_head .= $page_head;
        }

        return $this;
    }

    /**
     * Set page breadcrumb
     *
     * This must be set before page opening
     *
     * @param   array|null  $elements   The elements
     * @param   array       $options    The options
     */
    final public function setPageBreadcrumb(?array $elements = null, array $options = []): Page
    {
        $this->page_breadcrumb = ['elements' => $elements, 'options' => $options];

        return $this;
    }

    /**
     * Set page HTML body content
     *
     * This must be set before page opening
     *
     * @param   string|null     $page_content   The HTML body
     */
    final public function setPageContent(?string $page_content): Page
    {
        if (is_string($page_content)) {
            $this->page_content .= $page_content;
        }

        return $this;
    }

    /**
     * Set Help block names
     *
     * This must be set before page opening
     *
     * @param   string|Object  ...$page_help   The help blocks names
     */
    final public function setPageHelp(string|Object ...$page_help): Page
    {
        $this->page_help = $page_help;

        return $this;
    }

    /**
     * Get required permissions to load page
     *
     * Permissions must be :
     * a comma separated list of permission 'admin,media',
     * or null for superAdmin,
     * or false for no permissions
     *
     * @return string|null|false The permissions
     */
    abstract protected function getPermissions(): string|null|false;

    /**
     * Do something after contruct
     *
     * Note that page Action use this method to process actions.
     * This method returns :
     * - Null if nothing done, current process stop, if extists parent process goes on.
     * - Bool else, process goes on and stop, if exists parent process is not executed.
     *
     * @return  bool|null   Prepend result
     */
    protected function getPagePrepend(): ?bool
    {
        return true;
    }

    /**
     * Get Action instance
     *
     * If page contains Action, load instance from here.
     * It wil be accessible from $this->action
     */
    protected function getActionInstance(): ?Action
    {
        return null;
    }

    /**
     * Get Filter instance
     *
     * If page contains list Filter, load instance from here.
     * It wil be accessible from $this->filter
     */
    protected function getFilterInstance(): ?Filter
    {
        return null;
    }

    /**
     * Get Inventory instance
     *
     * If page contains list Inventory, load instance from here.
     * It wil be accessible from $this->inventory
     */
    protected function GetInventoryInstance(): ?Inventory
    {
        return null;
    }

    /**
     * Get page opening for non standard type
     *
     * This method must echo what there is to display.
     *
     * This method will be called if @var $page_type is unknow.
     * Usefull for custom page.
     */
    protected function getPageBegin(): void
    {
        throw new AdminException('Unknow page type');
    }

    /**
     * Get page content
     *
     * This method must echo what there is to display.
     */
    protected function getPageContent(): void
    {
        echo $this->page_content;
    }

    /**
     * Get page closure for non standard type
     *
     * This method must echo what there is to display.
     *
     * This method will be called if @var $page_type is unknow.
     * Usefull for custom page.
     */
    protected function getPageEnd(): void
    {
        throw new AdminException('Unknow page type');
    }
    //@}
}
