<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Inventory\Inventory;

use ArrayObject;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\Pager;

/**
 * Admin users list helper.
 *
 * \Dotclear\Process\Admin\Inventory\Inventory\UserInventory
 *
 * @ingroup  Admin User Inventory
 */
class UserInventory extends Inventory
{
    /**
     * Display a user list.
     *
     * @param int    $page          The page
     * @param int    $nb_per_page   The number of per page
     * @param string $enclose_block The enclose block
     * @param bool   $filter        The filter flag
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false): void
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

            $cols = new ArrayObject($cols);
            dotclear()->behavior()->call('adminUserListHeader', $this->rs, $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';
            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            echo $pager->getLinks() . $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->userLine();
            }

            $fmt = fn ($title, $image) => sprintf('<img alt="%1$s" title="%1$s" src="?df=images/%2$s" /> %1$s', $title, $image);

            echo $blocks[1] .
                '<p class="info">' . __('Legend: ') .
                $fmt(__('admin'), 'admin.png') . ' - ' .
                $fmt(__('superadmin'), 'superadmin.png') .
                '</p>' .
                $blocks[2] . $pager->getLinks();
        }
    }

    /**
     * Get a user line.
     *
     * @return string The line
     */
    private function userLine(): string
    {
        $img        = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
        $img_status = '';

        $p = dotclear()->users()->getUserPermissions($this->rs->f('user_id'));

        if (isset($p[dotclear()->blog()->id]['p']['admin'])) {
            $img_status = sprintf($img, __('admin'), 'admin.png');
        }
        if ($this->rs->fInt('user_super')) {
            $img_status = sprintf($img, __('superadmin'), 'superadmin.png');
        }

        $res = '<tr class="line">';

        $cols = [
            'check' => '<td class="nowrap">' . Form::hidden(['nb_post[]'], $this->rs->fint('nb_post')) .
            Form::checkbox(['users[]'], $this->rs->f('user_id')) . '</td>',
            'username' => '<td class="maximal" scope="row"><a href="' .
            dotclear()->adminurl()->get('admin.user', ['id' => $this->rs->f('user_id')]) . '">' .
            $this->rs->f('user_id') . '</a>&nbsp;' . $img_status . '</td>',
            'first_name'   => '<td class="nowrap">' . Html::escapeHTML($this->rs->f('user_firstname')) . '</td>',
            'last_name'    => '<td class="nowrap">' . Html::escapeHTML($this->rs->f('user_name')) . '</td>',
            'display_name' => '<td class="nowrap">' . Html::escapeHTML($this->rs->f('user_displayname')) . '</td>',
            'entries'      => '<td class="nowrap count"><a href="' .
            dotclear()->adminurl()->get('admin.posts', ['user_id' => $this->rs->f('user_id')]) . '">' .
            $this->rs->f('nb_post') . '</a></td>',
        ];

        $cols = new ArrayObject($cols);
        dotclear()->behavior()->call('adminUserListValue', $this->rs, $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
