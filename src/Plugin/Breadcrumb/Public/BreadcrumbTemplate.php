<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Public;

// Dotclear\Plugin\Breadcrumb\Public\BreadcrumbTemplate
use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Dt;
use Dotclear\Helper\L10n;

/**
 * Public templates for plugin Breacrumb.
 *
 * @ingroup  Plugin Breadcrumb Template
 */
class BreadcrumbTemplate
{
    // \cond
    // php tags break doxygen parser...
    private static $toff = ' ?>';
    private static $ton  = '<?php ';
    // \endcond

    public function __construct()
    {
        App::core()->template()->addValue('Breadcrumb', [$this, 'tplValueBreadcrumb']);
    }

    // Template function
    public function tplValueBreadcrumb(ArrayObject $attr): string
    {
        $separator = $attr['separator'] ?? '';

        return self::$ton . 'echo ' . __CLASS__ . '::tplDisplayBreadcrumb(' .
        "'" . addslashes($separator) . "'" .
            ');' . self::$toff;
    }

    public static function tplDisplayBreadcrumb(string $separator): string
    {
        $ret = '';

        // Check if breadcrumb enabled for the current blog
        if (!App::core()->blog()->settings()->get('breadcrumb')->get('breadcrumb_enabled')) {
            return $ret;
        }

        if ('' == $separator) {
            $separator = ' &rsaquo; ';
        }

        // Get current page
        $page = App::core()->context()->page_number();

        switch (App::core()->url()->type) {
            case 'static':
                // Static home
                $ret = '<span id="bc-home">' . __('Home') . '</span>';

                break;

            case 'default':
                if (App::core()->blog()->settings()->get('system')->get('static_home')) {
                    // Static home and on (1st) blog page
                    $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                    $ret .= $separator . __('Blog');
                } else {
                    // Home (first page only)
                    $ret = '<span id="bc-home">' . __('Home') . '</span>';
                    if (App::core()->context()->get('cur_lang')) {
                        $langs = L10n::getISOCodes();
                        $ret .= $separator . ($langs[App::core()->context()->get('cur_lang')] ?? App::core()->context()->get('cur_lang'));
                    }
                }

                break;

            case 'default-page':
                // Home or blog page`(page 2 to n)
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                if (App::core()->blog()->settings()->get('system')->get('static_home')) {
                    $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('posts') . '">' . __('Blog') . '</a>';
                } else {
                    if (App::core()->context()->get('cur_lang')) {
                        $langs = L10n::getISOCodes();
                        $ret .= $separator . ($langs[App::core()->context()->get('cur_lang')] ?? App::core()->context()->get('cur_lang'));
                    }
                }
                $ret .= $separator . sprintf(__('page %d'), $page);

                break;

            case 'category':
                // Category
                $ret        = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                $categories = App::core()->blog()->categories()->getCategoryParents(App::core()->context()->get('categories')->fInt('cat_id'));
                while ($categories->fetch()) {
                    $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('category', $categories->f('cat_url')) . '">' . $categories->f('cat_title') . '</a>';
                }
                if (0 == $page) {
                    $ret .= $separator . App::core()->context()->get('categories')->f('cat_title');
                } else {
                    $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('category', App::core()->context()->get('categories')->f('cat_url')) . '">' . App::core()->context()->get('categories')->f('cat_title') . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case 'post':
                // Post
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                if (App::core()->context()->get('posts')->fInt('cat_id')) {
                    // Parents cats of post's cat
                    $categories = App::core()->blog()->categories()->getCategoryParents(App::core()->context()->get('posts')->fInt('cat_id'));
                    while ($categories->fetch()) {
                        $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('category', $categories->f('cat_url')) . '">' . $categories->f('cat_title') . '</a>';
                    }
                    // Post's cat
                    $categories = App::core()->blog()->categories()->getCategory(App::core()->context()->get('posts')->fInt('cat_id'));
                    $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('category', $categories->f('cat_url')) . '">' . $categories->f('cat_title') . '</a>';
                }
                $ret .= $separator . App::core()->context()->get('posts')->f('post_title');

                break;

            case 'lang':
                // Lang
                $ret   = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                $langs = L10n::getISOCodes();
                $ret .= $separator . ($langs[App::core()->context()->get('cur_lang')] ?? App::core()->context()->get('cur_lang'));

                break;

            case 'archive':
                // Archives
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                if (!App::core()->context()->get('archives')) {
                    // Global archives
                    $ret .= $separator . __('Archives');
                } else {
                    // Month archive
                    $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('archive') . '">' . __('Archives') . '</a>';
                    $ret .= $separator . dt::dt2str('%B %Y', App::core()->context()->get('archives')->f('dt'));
                }

                break;

            case 'pages':
                // Page
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . App::core()->context()->get('posts')->f('post_title');

                break;

            case 'tags':
                // All tags
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . __('All tags');

                break;

            case 'tag':
                // Tag
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('tags') . '">' . __('All tags') . '</a>';
                if (0 == $page) {
                    $ret .= $separator . App::core()->context()->get('meta')->f('meta_id');
                } else {
                    $ret .= $separator . '<a href="' . App::core()->blog()->getURLFor('tag', rawurlencode(App::core()->context()->get('meta')->f('meta_id'))) . '">' . App::core()->context()->get('meta')->f('meta_id') . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case 'search':
                // Search
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                if (0 == $page) {
                    $ret .= $separator . __('Search:') . ' ' . App::core()->url()->search_string;
                } else {
                    $ret .= $separator . '<a href="' . App::core()->blog()->url . '?q=' . rawurlencode(App::core()->url()->search_string) . '">' . __('Search:') . ' ' . App::core()->url()->search_string . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case '404':
                // 404
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . __('404');

                break;

            default:
                $ret = '<a id="bc-home" href="' . App::core()->blog()->url . '">' . __('Home') . '</a>';
                // --BEHAVIOR-- publicBreadcrumb
                // Should specific breadcrumb if any, will be added after home page url
                $special = App::core()->behavior()->call('publicBreadcrumb', App::core()->url()->type, $separator);
                if ($special) {
                    $ret .= $separator . $special;
                }

                break;
        }

        // Encapsulate breadcrumb in <p>…</p>
        if (!App::core()->blog()->settings()->get('breadcrumb')->get('breadcrumb_alone')) {
            $ret = '<p id="breadcrumb">' . $ret . '</p>';
        }

        return $ret;
    }
}
