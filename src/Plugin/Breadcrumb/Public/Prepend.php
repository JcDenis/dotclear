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

use function Dotclear\core;

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
        core()->tpl->addValue('Breadcrumb', [__CLASS__, 'tplValueBreadcrumb']);
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
        core()->blog->settings->addNameSpace('breadcrumb');
        if (!core()->blog->settings->breadcrumb->breadcrumb_enabled) {
            return $ret;
        }

        if ($separator == '') {
            $separator = ' &rsaquo; ';
        }

        // Get current page
        $page = core()->context->page_number();

        switch (core()->url->type) {

            case 'static':
                // Static home
                $ret = '<span id="bc-home">' . __('Home') . '</span>';

                break;

            case 'default':
                if (core()->blog->settings->system->static_home) {
                    // Static home and on (1st) blog page
                    $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                    $ret .= $separator . __('Blog');
                } else {
                    // Home (first page only)
                    $ret = '<span id="bc-home">' . __('Home') . '</span>';
                    if (core()->context->cur_lang) {
                        $langs = L10n::getISOCodes();
                        $ret .= $separator . ($langs[core()->context->cur_lang] ?? core()->context->cur_lang);
                    }
                }

                break;

            case 'default-page':
                // Home or blog page`(page 2 to n)
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                if (core()->blog->settings->system->static_home) {
                    $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('posts') . '">' . __('Blog') . '</a>';
                } else {
                    if (core()->context->cur_lang) {
                        $langs = L10n::getISOCodes();
                        $ret .= $separator . ($langs[core()->context->cur_lang] ?? core()->context->cur_lang);
                    }
                }
                $ret .= $separator . sprintf(__('page %d'), $page);

                break;

            case 'category':
                // Category
                $ret        = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                $categories = core()->blog->getCategoryParents((int) core()->context->categories->cat_id);
                while ($categories->fetch()) {
                    $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('category', $categories->cat_url) . '">' . $categories->cat_title . '</a>';
                }
                if ($page == 0) {
                    $ret .= $separator . core()->context->categories->cat_title;
                } else {
                    $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('category', core()->context->categories->cat_url) . '">' . core()->context->categories->cat_title . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case 'post':
                // Post
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                if (core()->context->posts->cat_id) {
                    // Parents cats of post's cat
                    $categories = core()->blog->getCategoryParents((int) core()->context->posts->cat_id);
                    while ($categories->fetch()) {
                        $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('category', $categories->cat_url) . '">' . $categories->cat_title . '</a>';
                    }
                    // Post's cat
                    $categories = core()->blog->getCategory((int) core()->context->posts->cat_id);
                    $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('category', $categories->cat_url) . '">' . $categories->cat_title . '</a>';
                }
                $ret .= $separator . core()->context->posts->post_title;

                break;

            case 'lang':
                // Lang
                $ret   = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                $langs = L10n::getISOCodes();
                $ret .= $separator . ($langs[core()->context->cur_lang] ?? core()->context->cur_lang);

                break;

            case 'archive':
                // Archives
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                if (!core()->context->archives) {
                    // Global archives
                    $ret .= $separator . __('Archives');
                } else {
                    // Month archive
                    $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('archive') . '">' . __('Archives') . '</a>';
                    $ret .= $separator . dt::dt2str('%B %Y', core()->context->archives->dt);
                }

                break;

            case 'pages':
                // Page
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                $ret .= $separator . core()->context->posts->post_title;

                break;

            case 'tags':
                // All tags
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                $ret .= $separator . __('All tags');

                break;

            case 'tag':
                // Tag
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('tags') . '">' . __('All tags') . '</a>';
                if ($page == 0) {
                    $ret .= $separator . core()->context->meta->meta_id;
                } else {
                    $ret .= $separator . '<a href="' . core()->blog->url . core()->url->getURLFor('tag', rawurlencode(core()->context->meta->meta_id)) . '">' . core()->context->meta->meta_id . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case 'search':
                // Search
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                if ($page == 0) {
                    $ret .= $separator . __('Search:') . ' ' . $GLOBALS['_search'];
                } else {
                    $ret .= $separator . '<a href="' . core()->blog->url . '?q=' . rawurlencode($GLOBALS['_search']) . '">' . __('Search:') . ' ' . $GLOBALS['_search'] . '</a>';
                    $ret .= $separator . sprintf(__('page %d'), $page);
                }

                break;

            case '404':
                // 404
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                $ret .= $separator . __('404');

                break;

            default:
                $ret = '<a id="bc-home" href="' . core()->blog->url . '">' . __('Home') . '</a>';
                # --BEHAVIOR-- publicBreadcrumb
                # Should specific breadcrumb if any, will be added after home page url
                $special = core()->behaviors->call('publicBreadcrumb', core()->url->type, $separator);
                if ($special) {
                    $ret .= $separator . $special;
                }

                break;
        }

        # Encapsulate breadcrumb in <p>…</p>
        if (!core()->blog->settings->breadcrumb->breadcrumb_alone) {
            $ret = '<p id="breadcrumb">' . $ret . '</p>';
        }

        return $ret;
    }
}
