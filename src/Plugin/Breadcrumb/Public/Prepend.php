<?php
/**
 * @class Dotclear\Plugin\Breadcrumb\Public\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBreadcrumb
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Breadcrumb\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;

use Dotclear\Utils\Dt;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public static function loadModule(): void
    {
        dotclear()->template()->addValue('Breadcrumb', [__CLASS__, 'tplValueBreadcrumb']);
    }

    # Template function
    public static function tplValueBreadcrumb($attr)
    {
        $separator = $attr['separator'] ?? '';

        return '<?php echo ' . __CLASS__ . '::tplDisplayBreadcrumb(' .
        "'" . addslashes($separator) . "'" .
            '); ?>';
    }

    public static function tplDisplayBreadcrumb($separator)
    {
        $ret = '';

        # Check if breadcrumb enabled for the current blog
        dotclear()->blog()->settings->addNameSpace('breadcrumb');
        if (!dotclear()->blog()->settings->breadcrumb->breadcrumb_enabled) {
            return $ret;
        }

        if ($separator == '') {
            $separator = ' &rsaquo; ';
        }

        // Get current page
        $page = dotclear()->context()->page_number();

        switch (dotclear()->url()->type) {

            case 'static':
                // Static home
                $ret = '<span id="bc-home">' . __('Home') . '</span>';

                break;

            case 'default':
                if (dotclear()->blog()->settings->system->static_home) {
                    // Static home and on (1st) blog page
                    $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                    $ret .= $separator . __('Blog');
                } else {
                    // Home (first page only)
                    $ret = '<span id="bc-home">' . __('Home') . '</span>';
                    if (dotclear()->context()->cur_lang) {
                        $langs = L10n::getISOCodes();
                        $ret .= $separator . ($langs[dotclear()->context()->cur_lang] ?? dotclear()->context()->cur_lang);
                    }
                }

                break;

            case 'default-page':
                // Home or blog page`(page 2 to n)
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                if (dotclear()->blog()->settings->system->static_home) {
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('posts') . '">' . __('Blog') . '</a>';
                } else {
                    if (dotclear()->context()->cur_lang) {
                        $langs = L10n::getISOCodes();
                        $ret .= $separator . ($langs[dotclear()->context()->cur_lang] ?? dotclear()->context()->cur_lang);
                    }
                }
                $ret .= $separator . sprintf(__('page %d'), $page);

                break;

            case 'category':
                // Category
                $ret        = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                $categories = dotclear()->blog()->categories()->getCategoryParents((int) dotclear()->context()->categories->cat_id);
                while ($categories->fetch()) {
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('category', $categories->cat_url) . '">' . $categories->cat_title . '</a>';
                }
                if ($page == 0) {
                    $ret .= $separator . dotclear()->context()->categories->cat_title;
                } else {
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('category', dotclear()->context()->categories->cat_url) . '">' . dotclear()->context()->categories->cat_title . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case 'post':
                // Post
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                if (dotclear()->context()->posts->cat_id) {
                    // Parents cats of post's cat
                    $categories = dotclear()->blog()->categories()->getCategoryParents((int) dotclear()->context()->posts->cat_id);
                    while ($categories->fetch()) {
                        $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('category', $categories->cat_url) . '">' . $categories->cat_title . '</a>';
                    }
                    // Post's cat
                    $categories = dotclear()->blog()->categories()->getCategory((int) dotclear()->context()->posts->cat_id);
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('category', $categories->cat_url) . '">' . $categories->cat_title . '</a>';
                }
                $ret .= $separator . dotclear()->context()->posts->post_title;

                break;

            case 'lang':
                // Lang
                $ret   = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                $langs = L10n::getISOCodes();
                $ret .= $separator . ($langs[dotclear()->context()->cur_lang] ?? dotclear()->context()->cur_lang);

                break;

            case 'archive':
                // Archives
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                if (!dotclear()->context()->archives) {
                    // Global archives
                    $ret .= $separator . __('Archives');
                } else {
                    // Month archive
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('archive') . '">' . __('Archives') . '</a>';
                    $ret .= $separator . dt::dt2str('%B %Y', dotclear()->context()->archives->dt);
                }

                break;

            case 'pages':
                // Page
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . dotclear()->context()->posts->post_title;

                break;

            case 'tags':
                // All tags
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . __('All tags');

                break;

            case 'tag':
                // Tag
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('tags') . '">' . __('All tags') . '</a>';
                if ($page == 0) {
                    $ret .= $separator . dotclear()->context()->meta->meta_id;
                } else {
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . dotclear()->url()->getURLFor('tag', rawurlencode(dotclear()->context()->meta->meta_id)) . '">' . dotclear()->context()->meta->meta_id . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case 'search':
                // Search
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                if ($page == 0) {
                    $ret .= $separator . __('Search:') . ' ' . $GLOBALS['_search'];
                } else {
                    $ret .= $separator . '<a href="' . dotclear()->blog()->url . '?q=' . rawurlencode($GLOBALS['_search']) . '">' . __('Search:') . ' ' . $GLOBALS['_search'] . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case '404':
                // 404
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                $ret .= $separator . __('404');

                break;

            default:
                $ret = '<a id="bc-home" href="' . dotclear()->blog()->url . '">' . __('Home') . '</a>';
                # --BEHAVIOR-- publicBreadcrumb
                # Should specific breadcrumb if any, will be added after home page url
                $special = dotclear()->behavior()->call('publicBreadcrumb', dotclear()->url()->type, $separator);
                if ($special) {
                    $ret .= $separator . $special;
                }

                break;
        }

        # Encapsulate breadcrumb in <p>…</p>
        if (!dotclear()->blog()->settings->breadcrumb->breadcrumb_alone) {
            $ret = '<p id="breadcrumb">' . $ret . '</p>';
        }

        return $ret;
    }
}
