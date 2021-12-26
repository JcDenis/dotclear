<?php
/**
 * @class Dotclear\Admin\Page\PostsPopup
 * @brief Dotclear admin posts popup list page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Catalog\PostMiniCatalog;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PostsPopup extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);

        $this->check('usage,contentadmin');

        $q         = !empty($_GET['q']) ? $_GET['q'] : null;
        $plugin_id = !empty($_GET['plugin_id']) ? Html::sanitizeURL($_GET['plugin_id']) : '';

        $page        = !empty($_GET['page']) ? max(1, (integer) $_GET['page']) : 1;
        $nb_per_page = 10;

        $type = !empty($_GET['type']) ? $_GET['type'] : null;

        $post_types = $core->getPostTypes();
        $type_combo = [];
        foreach ($post_types as $k => $v) {
            $type_combo[__($k)] = (string) $k;
        }
        if (!in_array($type, $type_combo)) {
            $type = null;
        }

        $params               = [];
        $params['limit']      = [(($page - 1) * $nb_per_page), $nb_per_page];
        $params['no_content'] = true;
        $params['order']      = 'post_dt DESC';

        if ($q) {
            $params['search'] = $q;
        }

        if ($type) {
            $params['post_type'] = $type;
        }
/*
        if ($core->themes === null) {
            # -- Loading themes, may be useful for some configurable theme --
            $core->loadThemeClass();
            $core->themes->loadModules($core->blog->themes_path, null);
        }
*/
        $this->openPopup(__('Add a link to an entry'),
            static::jsLoad('js/_posts_list.js') .
            static::jsLoad('js/_popup_posts.js') .
            $core->behaviors->call('adminPopupPosts', $plugin_id));

        echo '<h2 class="page-title">' . __('Add a link to an entry') . '</h2>';

        echo '<form action="' . $core->adminurl->get('admin.posts.popup') . '" method="get">' .
        '<p><label for="type" class="classic">' . __('Entry type:') . '</label> ' . Form::combo('type', $type_combo, $type) . '' .
        '<noscript><div><input type="submit" value="' . __('Ok') . '" /></div></noscript>' .
        Form::hidden('plugin_id', Html::escapeHTML($plugin_id)) . '</p>' .
            '</form>';

        echo '<form action="' . $core->adminurl->get('admin.posts.popup') . '" method="get">' .
        '<p><label for="q" class="classic">' . __('Search entry:') . '</label> ' . Form::field('q', 30, 255, Html::escapeHTML($q)) .
        ' <input type="submit" value="' . __('Search') . '" />' .
        Form::hidden('plugin_id', Html::escapeHTML($plugin_id)) .
        Form::hidden('type', Html::escapeHTML($type)) .
            '</p></form>';

        $post_list = null;

        try {
            $posts     = $core->blog->getPosts($params);
            $counter   = $core->blog->getPosts($params, true);
            $post_list = new PostMiniCatalog($core, $posts, $counter->f(0));
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }

        echo '<div id="form-entries">'; # I know it's not a form but we just need the ID
        $post_list->display($page, $nb_per_page);
        echo '</div>';

        echo '<p><button type="button" id="link-insert-cancel">' . __('cancel') . '</button></p>';

        $this->closePopup();
    }
}
