<?php
/**
 * @class Dotclear\Admin\Catalog\UserCatalog
 * @brief Dotclear admin list helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Catalog;

use Dotclear\Core\Core;

use Dotclear\Admin\Pager;
use Dotclear\Admin\Catalog;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class UserCatalog extends Catalog
{
    /**
     * Display a user list
     *
     * @param      integer  $page           The page
     * @param      integer  $nb_per_page    The number of per page
     * @param      string   $enclose_block  The enclose block
     * @param      bool     $filter         The filter flag
     */
    public function display($page, $nb_per_page, $enclose_block = '', $filter = false)
    {
        if ($this->rs->isEmpty()) {
            if ($filter) {
                echo '<p><strong>' . __('No user matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No user') . '</strong></p>';
            }
        } else {
            $pager = new Pager($page, $this->rs_count, $nb_per_page, 10);

            $html_block = '<div class="table-outer clear">' .
                '<table>';

            if ($filter) {
                $html_block .= '<caption>' . sprintf(__('List of %s users match the filter.'), $this->rs_count) . '</caption>';
            } else {
                $html_block .= '<caption class="hidden">' . __('Users list') . '</caption>';
            }

            $cols = [
                'username'     => '<th colspan="2" scope="col" class="first">' . __('Username') . '</th>',
                'first_name'   => '<th scope="col">' . __('First Name') . '</th>',
                'last_name'    => '<th scope="col">' . __('Last Name') . '</th>',
                'display_name' => '<th scope="col">' . __('Display name') . '</th>',
                'entries'      => '<th scope="col" class="nowrap">' . __('Entries (all types)') . '</th>',
            ];

            $cols = new \ArrayObject($cols);
            $this->core->callBehavior('adminUserListHeader', $this->core, $this->rs, $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            echo $pager->getLinks();

            $blocks = explode('%s', $html_block);

            echo $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->userLine();
            }

            echo $blocks[1];

            $fmt = function ($title, $image) {
                return sprintf('<img alt="%1$s" title="%1$s" src="images/%2$s" /> %1$s', $title, $image);
            };
            echo '<p class="info">' . __('Legend: ') .
                $fmt(__('admin'), 'admin.png') . ' - ' .
                $fmt(__('superadmin'), 'superadmin.png') .
                '</p>';

            echo $blocks[2];

            echo $pager->getLinks();
        }
    }

    /**
     * Get a user line
     *
     * @return     string
     */
    private function userLine()
    {
        $img        = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        $img_status = '';

        $p = $this->core->getUserPermissions($this->rs->user_id);

        if (isset($p[$this->core->blog->id]['p']['admin'])) {
            $img_status = sprintf($img, __('admin'), 'admin.png');
        }
        if ($this->rs->user_super) {
            $img_status = sprintf($img, __('superadmin'), 'superadmin.png');
        }

        $res = '<tr class="line">';

        $cols = [
            'check' => '<td class="nowrap">' . Form::hidden(['nb_post[]'], (int) $this->rs->nb_post) .
            Form::checkbox(['users[]'], $this->rs->user_id) . '</td>',
            'username' => '<td class="maximal" scope="row"><a href="' .
            $this->core->adminurl->get('admin.user', ['id' => $this->rs->user_id]) . '">' .
            $this->rs->user_id . '</a>&nbsp;' . $img_status . '</td>',
            'first_name'   => '<td class="nowrap">' . Html::escapeHTML($this->rs->user_firstname) . '</td>',
            'last_name'    => '<td class="nowrap">' . Html::escapeHTML($this->rs->user_name) . '</td>',
            'display_name' => '<td class="nowrap">' . Html::escapeHTML($this->rs->user_displayname) . '</td>',
            'entries'      => '<td class="nowrap count"><a href="' .
            $this->core->adminurl->get('admin.posts', ['user_id' => $this->rs->user_id]) . '">' .
            $this->rs->nb_post . '</a></td>',
        ];

        $cols = new \ArrayObject($cols);
        $this->core->callBehavior('adminUserListValue', $this->core, $this->rs, $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
