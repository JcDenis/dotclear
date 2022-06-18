<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Page;

// Dotclear\Process\Admin\Page\AbstractPage
use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Statistic;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Filter\Filters;
use Dotclear\Process\Admin\Inventory\Inventory;
use Exception;

/**
 * Admin page helper.
 *
 * Properties of child class should start with
 * class or handler name to prevent inteferance with
 * this class properties.
 *
 * @ingroup  Admin
 */
abstract class AbstractPage
{
    /**
     * @var null|string $page_type
     *                  Page type
     */
    private $page_type;

    /**
     * @var string $page_title
     *             Page title
     */
    private $page_title = '';

    /**
     * @var string $page_head
     *             Page head
     */
    private $page_head = '';

    /**
     * @var string $page_content
     *             Page content
     */
    private $page_content = '';

    /**
     * @var array<int,ArrayObject|string> $page_help
     *                                    Help blocks names
     */
    private $page_help = [];

    /**
     * @var array<string,mixed> $page_breadcrumb
     *                          Page breadcrumb (brut))
     */
    private $page_breadcrumb = ['elements' => null, 'options' => []];

    /**
     * @var bool $page_xframe_loaded
     *           Load once xframe
     */
    private $page_xframe_loaded = false;

    /**
     * @var null|object $action
     *                  Action instance
     */
    protected $action;

    /**
     * @var null|object $filter
     *                  Filters instance
     */
    protected $filter;

    /**
     * @var null|object $inventory
     *                  Inventory instance
     */
    protected $inventory;

    /**
     * @var array<string,mixed> $options
     *                          Misc options for page content
     */
    protected $options = [];

    /**
     * Constructor.
     *
     * Check user permissions to load this page
     *
     * @param string $handler Used handler name
     */
    public function __construct(protected string $handler = 'admin.home')
    {
        $permissions = $this->getPermissions();

        // No permissions required for the page or user is Super Admin
        if (true === $permissions || App::core()->user()->isSuperAdmin()) {
            return;
        }

        // User has not required permissions
        if (is_string($permissions) && App::core()->blog() && App::core()->user()->check($this->getPermissions(), App::core()->blog()->id)) {
            return;
        }

        // Check if dashboard is not the current page and if it is granted for the user
        if (is_string($permissions) && 'admin.home' != $this->handler && App::core()->blog() && App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
            // Go back to the dashboard
            App::core()->adminurl()->redirect('admin.home');
        }

        // On all other case, user has not enought permissions, remove its session
        if (session_id()) {
            App::core()->session()->destroy();
        }
        // Then go to auth page
        App::core()->adminurl()->redirect('admin.auth');
    }

    /**
     * Process page display.
     *
     * Split process into readable methods
     */
    final public function pageProcess(): void
    {
        // Load into page usefull class instance, type is verified by abstract class type hint.
        try {
            // Load and process page Action
            if (null !== ($action_class = $this->getActionInstance())) {
                $this->action = $action_class;
                $this->action->pageProcess();
            }

            // Load list Filter
            if (null !== ($filter_class = $this->getFilterInstance())) {
                $this->filter = $filter_class;
            }

            // Load list Inventory
            if (null !== ($inventory_class = $this->getInventoryInstance())) {
                $this->inventory = $inventory_class;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        // Get page Prepend actions (stop process loop on null)
        if (null === ($action = $this->getPagePrepend())) {
            return;
        }

        // Open specific type of page
        match ($this->page_type) {
            null, 'full' => $this->pageOpen(),
            'plugin'     => $this->pageOpenPlugin(),
            'popup'      => $this->pageOpenPopup(),
            'standalone' => '',
            default      => $this->getPageBegin(),
        };

        $this->pageBreadcrumb();

        // Get notices
        echo App::core()->notice()->getHtmlNotices();

        // Get page content
        $this->getPageContent();

        // Get page help
        $this->pageHelp();

        // Close specific type of page
        match ($this->page_type) {
            null, 'full', 'plugin' => $this->pageClose(),
            'popup'      => $this->pageClosePopup(),
            'standalone' => '',
            default      => $this->getPageEnd(),
        };

        if (null !== $action) {
            exit;
        }
    }

    // / @name Page internal methods
    // @{
    /**
     * The top of a popup.
     */
    public function pageOpen(): void
    {
        $js = [];

        // List of user's blogs
        if (1 == App::core()->user()->getBlogCount() || 20 < App::core()->user()->getBlogCount()) {
            $blog_box = '<p>' . __('Blog:') . ' <strong title="' . Html::escapeHTML(App::core()->blog()->url) . '">' .
            Html::escapeHTML(App::core()->blog()->name) . '</strong>';

            if (20 < App::core()->user()->getBlogCount()) {
                $blog_box .= ' - <a href="' . App::core()->adminurl()->get('admin.blogs') . '">' . __('Change blog') . '</a>';
            }
            $blog_box .= '</p>';
        } else {
            $param = new Param();
            $param->set('order', 'LOWER(blog_name)');
            $param->set('limit', 20);

            $rs_blogs = App::core()->blogs()->getBlogs(param: $param);
            $blogs    = [];
            while ($rs_blogs->fetch()) {
                $blogs[Html::escapeHTML($rs_blogs->field('blog_name') . ' - ' . $rs_blogs->field('blog_url'))] = $rs_blogs->field('blog_id');
            }
            $blog_box = '<p><label for="switchblog" class="classic">' . __('Blogs:') . '</label> ' .
            App::core()->nonce()->form() . Form::combo('switchblog', $blogs, App::core()->blog()->id) .
            Form::hidden(['redir'], $_SERVER['REQUEST_URI']) .
            '<input type="submit" value="' . __('ok') . '" class="hidden-if-js" /></p>';
        }

        // Display
        /** @var ArrayObject<string, string> */
        $headers = new ArrayObject();

        // Content-Type
        $headers['content-type'] = 'Content-Type: text/html; charset=UTF-8';

        // Referrer Policy for admin pages
        $headers['referrer'] = 'Referrer-Policy: strict-origin';

        // Prevents Clickjacking as far as possible
        if (isset($this->options['x-frame-allow'])) {
            $this->setXFrameOptions($headers, $this->options['x-frame-allow']);
        } else {
            $this->setXFrameOptions($headers);
        }

        // Content-Security-Policy (only if safe mode if not active, it may help)
        $system = App::core()->blog()->settings()->getGroup('system');
        if (!App::core()->rescue() && $system->getSetting('csp_admin_on')) {
            // Get directives from settings if exist, else set defaults
            /** @var ArrayObject<string, string> */
            $csp = new ArrayObject();

            // SQlite Clearbricks driver does not allow using single quote at beginning or end of a field value
                                                                                // so we have to use neutral values (localhost and 127.0.0.1) for some CSP directives
            $csp_prefix = App::core()->con()->syntax() == 'sqlite' ? 'localhost ' : ''; // Hack for SQlite Clearbricks syntax
            $csp_suffix = App::core()->con()->syntax() == 'sqlite' ? ' 127.0.0.1' : ''; // Hack for SQlite Clearbricks syntax

            $csp['default-src'] = $system->getSetting('csp_admin_default') ?:
            $csp_prefix . "'self'" . $csp_suffix;
            $csp['script-src'] = $system->getSetting('csp_admin_script') ?:
            $csp_prefix . "'self' 'unsafe-eval'" . $csp_suffix;
            $csp['style-src'] = $system->getSetting('csp_admin_style') ?:
            $csp_prefix . "'self' 'unsafe-inline'" . $csp_suffix;
            $csp['img-src'] = $system->getSetting('csp_admin_img') ?:
            $csp_prefix . "'self' data: https://media.dotaddict.org blob:";

            // Cope with blog post preview (via public URL in iframe)
            if (!is_null(App::core()->blog()->host)) {
                $csp['default-src'] .= ' ' . parse_url(App::core()->blog()->host, PHP_URL_HOST);
                $csp['script-src']  .= ' ' . parse_url(App::core()->blog()->host, PHP_URL_HOST);
                $csp['style-src']   .= ' ' . parse_url(App::core()->blog()->host, PHP_URL_HOST);
            }
            // Cope with media display in media manager (via public URL)
            if (App::core()->media()) {
                $csp['img-src'] .= ' ' . parse_url(App::core()->media()->root_url, PHP_URL_HOST);
            } elseif (!is_null(App::core()->blog()->host)) {
                // Let's try with the blog URL
                $csp['img-src'] .= ' ' . parse_url(App::core()->blog()->host, PHP_URL_HOST);
            }
            // Allow everything in iframe (used by editors to preview public content)
            $csp['frame-src'] = '*';

            // --BEHAVIOR-- adminPageHTTPHeaderCSP, ArrayObject
            App::core()->behavior('adminPageHTTPHeaderCSP')->call($csp);

            // Construct CSP header
            $directives = [];
            foreach ($csp as $key => $value) {
                if ($value) {
                    $directives[] = $key . ' ' . $value;
                }
            }
            if (count($directives)) {
                $directives[]   = 'report-uri ' . App::core()->config()->get('admin_url') . '?handler=admin.cspreport';
                $report_only    = $system->getSetting('csp_admin_report_only') ? '-Report-Only' : '';
                $headers['csp'] = 'Content-Security-Policy' . $report_only . ': ' . implode(' ; ', $directives);
            }
        }

        // --BEHAVIOR-- adminPageHTTPHeaders, ArrayObject
        App::core()->behavior('adminPageHTTPHeaders')->call($headers);

        foreach ($headers as $key => $value) {
            header($value);
        }

        $data_theme = App::core()->user()->preference()->get('interface')->get('theme');

        echo '<!DOCTYPE html>' .
        '<html lang="' . App::core()->user()->getInfo('user_lang') . '" data-theme="' . $data_theme . '">' . "\n" .
        "<head>\n" .
        '  <meta charset="UTF-8" />' . "\n" .
        '  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />' . "\n" .
        '  <meta name="GOOGLEBOT" content="NOSNIPPET" />' . "\n" .
        '  <meta name="viewport" content="width=device-width, initial-scale=1.0" />' . "\n" .
        '  <title>' . Html::escapeHTML(
            $this->page_title . ' - ' .
                App::core()->blog()->name . ' - ' .
                App::core()->config()->get('vendor_name') . ' - ' .
                App::core()->config()->get('core_version')
        ) . '</title>' . "\n";

        echo App::core()->resource()->preload('default.css') . App::core()->resource()->load('default.css');

        if ('rtl' == L10n::getLanguageTextDirection(L10n::lang())) {
            echo App::core()->resource()->load('default-rtl.css');
        }

        if (!App::core()->user()->preference()->get('interface')->get('hide_std_favicon')) {
            echo '<link rel="icon" type="image/png" href="?df=images/favicon96-login.png" />' . "\n" .
                '<link rel="shortcut icon" href="?df=images/favicon.ico" type="image/x-icon" />' . "\n";
        }
        if (App::core()->user()->preference()->get('interface')->get('htmlfontsize')) {
            $js['htmlFontSize'] = App::core()->user()->preference()->get('interface')->get('htmlfontsize');
        }
        $js['hideMoreInfo']   = (bool) App::core()->user()->preference()->get('interface')->get('hidemoreinfo');
        $js['showAjaxLoader'] = (bool) App::core()->user()->preference()->get('interface')->get('showajaxloader');
        $js['noDragDrop']     = (bool) App::core()->user()->preference()->get('accessibility')->get('nodragdrop');
        $js['debug']          = !App::core()->production();
        $js['showIp']         = App::core()->blog() && App::core()->blog()->id ? App::core()->user()->check('contentadmin', App::core()->blog()->id) : false;

        // Set some JSON data
        echo App::core()->resource()->json('dotclear_init', $js) .
        App::core()->resource()->common() .
        App::core()->resource()->toggles() .
        $this->page_head;

        // --BEHAVIOR-- adminPageHTMLHead, string, string
        App::core()->behavior('adminPageHTMLHead')->call($this->handler, $this->page_type);

        echo "</head>\n" .
        '<body id="dotclear-admin" class="no-js' .
        (App::core()->rescue() ? ' safe-mode' : '') .
        (App::core()->production() ? '' : ' debug-mode') .
        '">' . "\n" .

        '<header id="header" role="banner">' .
        '<h1><a href="' . App::core()->adminurl()->get('admin.home') . '"><span class="hidden">' . App::core()->config()->get('vendor_name') . '</span></a></h1>' . "\n";

        echo '<form action="' . App::core()->adminurl()->get('admin.home') . '" method="post" id="top-info-blog">' .
        $blog_box .
        '<p><a href="' . App::core()->blog()->url . '" class="outgoing" title="' . __('Go to site') .
        '">' . __('Go to site') . '<img src="?df=images/outgoing-link.svg" alt="" /></a>' .
        '</p></form>' .

        '<ul id="prelude">' .
        '<li><a href="#content">' . __('Go to the content') . '</a></li>' .
        '<li><a href="#main-menu">' . __('Go to the menu') . '</a></li>' .
        '<li><a href="#help">' . __('Go to help') . '</a></li>' .
        '</ul>' . "\n" .

        '<ul id="top-info-user">' .
        '<li><a class="' . (App::core()->adminurl()->is('admin.home') ? ' active' : '') . '" href="' . App::core()->adminurl()->get('admin.home') . '">' . __('My dashboard') . '</a></li>' .
        '<li><a class="smallscreen' . (App::core()->adminurl()->is('admin.user.pref') ? ' active' : '') . '" href="' . App::core()->adminurl()->get('admin.user.pref') . '">' . __('My preferences') . '</a></li>' .
        '<li><a href="' . App::core()->adminurl()->get('admin.home', ['logout' => 1]) . '" class="logout"><span class="nomobile">' . sprintf(__('Logout %s'), App::core()->user()->userID()) .
            '</span><img src="?df=images/logout.svg" alt="" /></a></li>' .
            '</ul>' .
            '</header>'; // end header

        echo '<div id="wrapper" class="clearfix">' . "\n" .
        '<div class="hidden-if-no-js collapser-box"><button type="button" id="collapser" class="void-btn">' .
        '<img class="collapse-mm visually-hidden" src="?df=images/collapser-hide.png" alt="' . __('Hide main menu') . '" />' .
        '<img class="expand-mm visually-hidden" src="?df=images/collapser-show.png" alt="' . __('Show main menu') . '" />' .
            '</button></div>' .
            '<main id="main" role="main">' . "\n" .
            '<div id="content" class="clearfix">' . "\n";

        // Safe mode
        if (App::core()->rescue()) {
            echo '<div class="warning" role="alert"><h3>' . __('Safe mode') . '</h3>' .
            '<p>' . __('You are in safe mode. All plugins have been temporarily disabled. Remind to log out then log in again normally to get back all functionalities') . '</p>' .
                '</div>';
        }
    }

    private function pageOpenPlugin(): void
    {
        echo '<html><head><title>' . $this->page_title . '</title>' .
            $this->page_head .
            '</script></head><body>';
    }

    /**
     * The top of a popup.
     */
    public function pageOpenPopup(): void
    {
        $js = [];

        // Display
        header('Content-Type: text/html; charset=UTF-8');

        // Referrer Policy for admin pages
        header('Referrer-Policy: strict-origin');

        // Prevents Clickjacking as far as possible
        header('X-Frame-Options: SAMEORIGIN'); // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+

        $data_theme = App::core()->user()->preference()->get('interface')->get('theme');

        echo '<!DOCTYPE html>' .
        '<html lang="' . App::core()->user()->getInfo('user_lang') . '" data-theme="' . $data_theme . '">' . "\n" .
        "<head>\n" .
        '  <meta charset="UTF-8" />' . "\n" .
        '  <meta name="viewport" content="width=device-width, initial-scale=1.0" />' . "\n" .
        '  <title>' . $this->page_title . ' - ' . Html::escapeHTML(App::core()->blog()->name) . ' - ' . Html::escapeHTML(App::core()->config()->get('vendor_name')) . ' - ' . App::core()->config()->get('core_version') . '</title>' . "\n" .
            '  <meta name="ROBOTS" content="NOARCHIVE,NOINDEX,NOFOLLOW" />' . "\n" .
            '  <meta name="GOOGLEBOT" content="NOSNIPPET" />' . "\n";

        echo App::core()->resource()->preload('default.css') . App::core()->resource()->load('default.css');

        if (L10n::getLanguageTextDirection(L10n::lang()) == 'rtl') {
            echo App::core()->resource()->load('default-rtl.css');
        }

        if (App::core()->user()->preference()->get('interface')->get('htmlfontsize')) {
            $js['htmlFontSize'] = App::core()->user()->preference()->get('interface')->get('htmlfontsize');
        }
        $js['hideMoreInfo']   = (bool) App::core()->user()->preference()->get('interface')->get('hidemoreinfo');
        $js['showAjaxLoader'] = (bool) App::core()->user()->preference()->get('interface')->get('showajaxloader');
        $js['noDragDrop']     = (bool) App::core()->user()->preference()->get('accessibility')->get('nodragdrop');
        $js['debug']          = !App::core()->production();

        // Set JSON data
        echo App::core()->resource()->json('dotclear_init', $js) .
        App::core()->resource()->common() .
        App::core()->resource()->toggles() .
        $this->page_head;

        // --BEHAVIOR-- adminPageHTMLHead, string, string
        App::core()->behavior('adminPageHTMLHead')->call($this->handler, $this->page_type);

        echo "</head>\n" .
            '<body id="dotclear-admin" class="popup' .
            (App::core()->rescue() ? ' safe-mode' : '') .
            (App::core()->production() ? '' : ' debug-mode') .
            '">' . "\n" .

            '<h1>' . App::core()->config()->get('vendor_name') . '</h1>' . "\n";

        echo '<div id="wrapper">' . "\n" .
            '<main id="main" role="main">' . "\n" .
            '<div id="content">' . "\n";
    }

    private function pageBreadcrumb(): void
    {
        $elements = $this->page_breadcrumb['elements'];
        $options  = $this->page_breadcrumb['options'];

        if (null === $elements) {
            return;
        }

        $with_home_link = $options['home_link'] ?? true;
        $hl             = $options['hl']        ?? true;
        $hl_pos         = $options['hl_pos']    ?? -1;
        // First item of array elements should be blog's name, System or Plugins
        $res = '<h2>' . ($with_home_link ?
            '<a class="go_home" href="' . App::core()->adminurl()->get('admin.home') . '">' .
            '<img class="go_home light-only" src="?df=css/dashboard.svg" alt="' . __('Go to dashboard') . '" />' .
            '<img class="go_home dark-only" src="?df=css/dashboard-dark.svg" alt="' . __('Go to dashboard') . '" />' .
            '</a>' :
            '<img class="go_home light-only" src="?df=css/dashboard-alt.svg" alt="" />' .
            '<img class="go_home dark-only" src="?df=css/dashboard-alt-dark.svg" alt="" />');
        $index = 0;
        if (0 > $hl_pos) {
            $hl_pos = count($elements) + $hl_pos;
        }
        foreach ($elements as $element => $url) {
            if ($hl && $index == $hl_pos) {
                $element = sprintf('<span class="page-title">%s</span>', $element);
            }
            $res .= ($with_home_link ? (1 == $index ? ' : ' : ' &rsaquo; ') : (0 == $index ? ' ' : ' &rsaquo; ')) .
                ($url ? '<a href="' . $url . '">' : '') . $element . ($url ? '</a>' : '');
            ++$index;
        }
        $res .= '</h2>';

        echo $res;
    }

    /**
     * Display Help block.
     */
    private function pageHelp(): void
    {
        if (!App::core()->user()->preference()) {
            return;
        }
        if (App::core()->user()->preference()->get('interface')->get('hidehelpbutton')) {
            return;
        }

        $args = new ArrayObject($this->page_help);

        // --BEHAVIOR-- adminPageHelpBlock, ArrayObject
        App::core()->behavior('adminPageHelpBlock')->call($args);

        if (!count($args)) {
            return;
        }

        $content = '';
        foreach ($args as $v) {
            if (empty($v)) {
                continue;
            }
            if ($v instanceof ArrayObject && isset($v['content'])) {
                $content .= $v['content'];

                continue;
            }

            if (!($f = App::core()->help()->context($v))) {
                continue;
            }
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
        App::core()->help()->flag(true);

        echo '<div id="help"><hr /><div class="help-content clear"><h3>' . __('Help about this page') . '</h3>' .
        $content .
        '</div>' .
        '<div id="helplink"><hr />' .
        '<p>' .
        sprintf(__('See also %s'), sprintf('<a href="' . App::core()->adminurl()->get('admin.help') . '">%s</a>', __('the global help'))) .
            '.</p>' .
            '</div></div>';
    }

    private function pageClose(): void
    {
        if (!App::core()->help()->flag() && !App::core()->user()->preference()->get('interface')->get('hidehelpbutton')) {
            echo sprintf(
                '<p id="help-button"><a href="%1$s" class="outgoing" title="%2$s">%2$s</a></p>',
                App::core()->adminurl()->get('admin.help'),
                __('Global help')
            );
        }

        echo "</div>\n" .  // End of #content
        "</main>\n" . // End of #main

        '<nav id="main-menu" role="navigation">' . "\n" .

        '<form id="search-menu" action="' . App::core()->adminurl()->get('admin.search') . '" method="get" role="search">' .
        '<p><label for="qx" class="hidden">' . __('Search:') . ' </label>' . Form::field('qx', 30, 255, '') .
        '<input type="submit" value="' . __('OK') . '" /></p>' .
            '</form>';

        foreach (App::core()->menu()->getGroups() as $section => $group) {
            echo $group->toHTML();
        }

        $text = sprintf(__('Thank you for using %s.'), 'Dotclear ' . App::core()->config()->get('core_version'));

        // --BEHAVIOR-- adminPageFooter, string
        $textAlt = App::core()->behavior('adminPageFooter')->call($text);
        if ('' != $textAlt) {
            $text = $textAlt;
        }
        $text = Html::escapeHTML($text);

        echo '</nav>' . "\n" . // End of #main-menu
        "</div>\n";       // End of #wrapper

        echo '<p id="gototop"><a href="#wrapper">' . __('Page top') . '</a></p>' . "\n";

        $figure = '

                (╯°□°)╯︵ ┻━┻

            ';

        echo '<footer id="footer" role="contentinfo">' .
            '<a href="https://dotclear.org/" title="' . $text . '">' .
            '<img src="?df=css/dc_logos/w-dotclear90.png" alt="' . $text . '" /></a></footer>' . "\n" .
            '<!-- ' . "\n" .
            $figure .
            ' -->' . "\n";

        if (!App::core()->production()) {
            echo $this->pageDebugInfo();
        }

        echo '</body></html>';
    }

    /**
     * Get HTML code of debug information.
     */
    private function pageDebugInfo(): string
    {
        $global_vars = implode(', ', array_keys($GLOBALS));

        $res = '<div id="debug"><div>' .
        '<p>PHP memory usage: ' . memory_get_usage() . ' (' . Files::size(memory_get_usage()) . ')</p>';

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

            /*
            xdebug configuration:
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
        $res .= '<p>Core elapsed time: ' . Statistic::time() . ' | Core consumed memory: ' . Statistic::memory() . '</p>';
        $res .= '<p>Dotclear autoloader provided files : ' . App::autoload()->getLoadsCount() . ' (' . App::autoload()->getRequestsCount() . ' requests)</p>';

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
            '</body></html>',
        ]);
    }

    /**
     * Sets the x frame options.
     *
     * @param array|ArrayObject $headers The headers
     * @param null|string       $origin  The origin
     */
    public function setXFrameOptions(array|ArrayObject $headers, ?string $origin = null): void
    {
        if ($this->page_xframe_loaded) {
            return;
        }

        if (null !== $origin) {
            $url                        = parse_url($origin);
            $headers['x-frame-options'] = sprintf('X-Frame-Options: %s', is_array($url) && isset($url['host']) ?
                ('ALLOW-FROM ' . (isset($url['scheme']) ? $url['scheme'] . ':' : '') . '//' . $url['host']) :
                'SAMEORIGIN');
        } else {
            $headers['x-frame-options'] = 'X-Frame-Options: SAMEORIGIN'; // FF 3.6.9+ Chrome 4.1+ IE 8+ Safari 4+ Opera 10.5+
        }
        $this->page_xframe_loaded = true;
    }
    // @}

    // / @name Page child class methods
    // @{
    /**
     * Set page type.
     *
     * This must be set before page opening
     *
     * type can be :
     * - null or 'full' for standard page
     * - 'popup',
     * - ...
     *
     * @param string $page_type The page type
     */
    final public function setPageType(string $page_type = null): AbstractPage
    {
        $this->page_type = is_string($page_type) ? $page_type : 'full';

        return $this;
    }

    /**
     * Set page title.
     *
     * This must be set before page opening
     *
     * @param null|string $page_title The page title
     */
    final public function setPageTitle(?string $page_title): AbstractPage
    {
        $this->page_title = is_string($page_title) ? $page_title : '';

        return $this;
    }

    /**
     * Set page HTML head content.
     *
     * This must be set before page opening
     *
     * @param null|string $page_head The HTML code for head
     */
    final public function setPageHead(?string $page_head): AbstractPage
    {
        if (is_string($page_head)) {
            $this->page_head .= $page_head;
        }

        return $this;
    }

    /**
     * Set page breadcrumb.
     *
     * This must be set before page opening
     *
     * @param null|array $elements The elements
     * @param array      $options  The options
     */
    final public function setPageBreadcrumb(?array $elements = null, array $options = []): AbstractPage
    {
        $this->page_breadcrumb = ['elements' => $elements, 'options' => $options];

        return $this;
    }

    /**
     * Set page HTML body content.
     *
     * This must be set before page opening
     *
     * @param null|string $page_content The HTML body
     */
    final public function setPageContent(?string $page_content): AbstractPage
    {
        if (is_string($page_content)) {
            $this->page_content .= $page_content;
        }

        return $this;
    }

    /**
     * Set Help block names.
     *
     * This must be set before page opening
     *
     * @param ArrayObject|string ...$page_help The help blocks names
     */
    final public function setPageHelp(string|ArrayObject ...$page_help): AbstractPage
    {
        $this->page_help = $page_help;

        return $this;
    }

    /**
     * Get required permissions to load page.
     *
     * Permissions must be :
     * a comma separated list of permission 'admin,media',
     * or empty string for super admin
     * or true to force allow
     * or false to force disallow
     *
     * @return bool|string The permissions
     */
    abstract protected function getPermissions(): string|bool;

    /**
     * Do something after contruct.
     *
     * Note that page Action use this method to process actions.
     * This method returns :
     * - Null if nothing done, current process stop, if extists parent process goes on.
     * - Bool else, process goes on and stop, if exists parent process is not executed.
     *
     * @return null|bool Prepend result
     */
    protected function getPagePrepend(): ?bool
    {
        return true;
    }

    /**
     * Get Action instance.
     *
     * If page contains Action, load instance from here.
     * It wil be accessible from $this->action
     */
    protected function getActionInstance(): ?Action
    {
        return null;
    }

    /**
     * Get Filter instance.
     *
     * If page contains list Filter, load instance from here.
     * It wil be accessible from $this->filter
     */
    protected function getFilterInstance(): ?Filters
    {
        return null;
    }

    /**
     * Get Inventory instance.
     *
     * If page contains list Inventory, load instance from here.
     * It wil be accessible from $this->inventory
     */
    protected function getInventoryInstance(): ?Inventory
    {
        return null;
    }

    /**
     * Get page opening for non standard type.
     *
     * This method must echo what there is to display.
     * This method will be called if page_type is unknown.
     * Usefull for custom page.
     */
    protected function getPageBegin(): void
    {
        throw new AdminException('Unknow page type');
    }

    /**
     * Get page content.
     *
     * This method must echo what there is to display.
     */
    protected function getPageContent(): void
    {
        echo $this->page_content;
    }

    /**
     * Get page closure for non standard type.
     *
     * This method must echo what there is to display.
     * This method will be called if page_type is unknown.
     * Usefull for custom page.
     */
    protected function getPageEnd(): void
    {
        throw new AdminException('Unknow page type');
    }
    // @}
}
