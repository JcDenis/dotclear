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
use Dotclear\Core\Media\Media;
use Dotclear\Core\Media\Manager\Item;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\File\Files;
use Dotclear\Process\Admin\Filter\Filter\MediaFilter;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Page\Pager;

/**
 * Admin media list helper.
 *
 * \Dotclear\Process\Admin\Inventory\Inventory\MediaInventory
 *
 * @ingroup  Admin Media Inventory
 */
class MediaInventory extends Inventory
{
    /**
     * Display a media list.
     *
     * @param MediaFilter $filters       The filters
     * @param string      $enclose_block The enclose block
     * @param bool        $query         If it's a query or not
     * @param string      $page_adminurl Page URL
     */
    public function display(MediaFilter $filters, string $enclose_block = '', bool $query = false, string $page_adminurl = 'admin.media'): void
    {
        $nb_items   = $this->rs_count - ($filters->get('d') ? 1 : 0);
        $nb_folders = $filters->get('d') ? -1 : 0;

        if ($filters->get('q') && !$query) {
            echo '<p><strong>' . __('No file matches the filter') . '</strong></p>';
        } elseif (1 > $nb_items) {
            echo '<p><strong>' . __('No file.') . '</strong></p>';
        }

        if ($this->rs_count && !($filters->get('q') && !$query)) {
            $pager = new Pager($filters->get('page'), $this->rs_count, $filters->get('nb'), 10);

            $items = $this->rs->rows();
            foreach ($items as $item) {
                if ($item->d) {
                    ++$nb_folders;
                }
            }
            $nb_files = $nb_items - $nb_folders;

            if ($filters->show() && $query) {
                $caption = sprintf(__('%d file matches the filter.', '%d files match the filter.', $nb_items), $nb_items);
            } else {
                $caption = ($nb_files && $nb_folders ?
                    sprintf(__('Nb of items: %d → %d folder(s) + %d file(s)'), $nb_items, $nb_folders, $nb_files) :
                    sprintf(__('Nb of items: %d'), $nb_items));
            }

            $group = ['dirs' => [], 'files' => []];
            for ($i = $pager->index_start, $j = 0; $i <= $pager->index_end; $i++, $j++) {
                $group[$items[$i]->d ? 'dirs' : 'files'][] = $this->mediaLine($filters, $items[$i], $j, $query, $page_adminurl);
            }

            if ('list' == $filters->get('file_mode')) {
                $table = sprintf(
                    '<div class="table-outer">' .
                    '<table class="media-items-bloc">' .
                    '<caption>' . $caption . '</caption>' .
                    '<tr>' .
                    '<th colspan="2" class="first">' . __('Name') . '</th>' .
                    '<th scope="col">' . __('Date') . '</th>' .
                    '<th scope="col">' . __('Size') . '</th>' .
                    '</tr>%s%s</table></div>',
                    implode($group['dirs']),
                    implode($group['files'])
                );
                $html_block = sprintf($enclose_block, $table, '');
            } else {
                $html_block = sprintf(
                    '%s%s<div class="media-stats"><p class="form-stats">' . $caption . '</p></div>',
                    !empty($group['dirs']) ? '<div class="folders-group">' . implode($group['dirs']) . '</div>' : '',
                    sprintf($enclose_block, '<div class="media-items-bloc">' . implode($group['files']), '') . '</div>'
                );
            }

            echo $pager->getLinks() . $html_block . $pager->getLinks();
        }
    }

    /**
     * Get a media line.
     *
     * @param MediaFilter $filters       The filters
     * @param Item        $f             The current media item
     * @param int         $i             The current index
     * @param bool        $query         If it's a query or not
     * @param string      $page_adminurl Page URL
     *
     * @return string The line
     */
    public function mediaLine(MediaFilter $filters, Item $f, int $i, bool $query = false, string $page_adminurl = 'admin.media'): string
    {
        $fname = $f->basename;
        $file  = $query ? $f->relname : $f->basename;

        $class = 'media-item-bloc'; // cope with js message for grid AND list
        $class .= 'list' == $filters->get('file_mode') ? '' : ' media-item media-col-' . ($i % 2);

        if ($f->d) {
            // Folder
            $link = dotclear()->adminurl()->get('admin.media', array_merge($filters->values(), ['d' => Html::sanitizeURL($f->relname)]));
            if ($f->parent) {
                $fname = '..';
                $class .= ' media-folder-up';
            } else {
                $class .= ' media-folder';
            }
        } else {
            // Item
            $params = new ArrayObject(array_merge($filters->values(), ['id' => $f->media_id]));

            dotclear()->behavior()->call('adminMediaURLParams', $params);

            $link = dotclear()->adminurl()->get('admin.media.item', (array) $params);
            if ($f->media_priv) {
                $class .= ' media-private';
            }
        }

        $maxchars = 34; // cope with design
        if (strlen($fname) > $maxchars) {
            $fname = substr($fname, 0, $maxchars - 4) . '...' . ($f->d ? '' : Files::getExtension($fname));
        }

        $act = '';
        if (!$f->d) {
            if (0 < $filters->get('select')) {
                if (1 == $filters->get('select')) {
                    // Single media selection button
                    $act .= '<a href="' . $link . '"><img src="?df=images/plus.png" alt="' . __('Select this file') . '" ' .
                    'title="' . __('Select this file') . '" /></a> ';
                } else {
                    // Multiple media selection checkbox
                    $act .= Form::checkbox(['medias[]', 'media_' . rawurlencode($file)], $file);
                }
            } else {
                // Item
                if ($filters->get('post_id')) {
                    // Media attachment button
                    $act .= '<a class="attach-media" title="' . __('Attach this file to entry') . '" href="' .
                    dotclear()->adminurl()->get(
                        'admin.post.media',
                        ['media_id' => $f->media_id, 'post_id' => $filters->get('post_id'), 'attach' => 1, 'link_type' => $filters->get('link_type')]
                    ) .
                    '">' .
                    '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '"/>' .
                        '</a>';
                }
                if ($filters->get('popup')) {
                    // Media insertion button
                    $act .= '<a href="' . $link . '"><img src="?df=images/plus.png" alt="' . __('Insert this file into entry') . '" ' .
                    'title="' . __('Insert this file into entry') . '" /></a> ';
                }
            }
        }
        if ($f->del) {
            // Deletion button or checkbox
            if (!$filters->get('popup') && !$f->d) {
                if (2 > $filters->get('select')) {
                    // Already set for multiple media selection
                    $act .= Form::checkbox(['medias[]', 'media_' . rawurlencode($file)], $file);
                }
            } else {
                $act .= '<a class="media-remove" ' .
                'href="' . dotclear()->adminurl()->get($page_adminurl, array_merge($filters->values(), ['remove' => rawurlencode($file)])) . '">' .
                '<img src="?df=images/trash.png" alt="' . __('Delete') . '" title="' . __('delete') . '" /></a>';
            }
        }

        $file_type  = explode('/', (string) $f->type);
        $class_open = 'class="modal-' . $file_type[0] . '" ';

        // Render markup
        if ('list' != $filters->get('file_mode')) {
            $res = '<div class="' . $class . '"><p><a class="media-icon media-link" href="' . rawurldecode($link) . '">' .
            '<img class="media-icon-square" src="' . $f->media_icon . '" alt="" />' . ($query ? $file : $fname) . '</a></p>';

            $lst = '';
            if (!$f->d) {
                $lst .= '<li>' . ($f->media_priv ? '<img class="media-private" src="?df=images/locker.png" alt="' . __('private media') . '">' : '') . $f->media_title . '</li>' .
                '<li>' .
                $f->media_dtstr . ' - ' .
                Files::size($f->size) . ' - ' .
                '<a ' . $class_open . 'href="' . $f->file_url . '">' . __('open') . '</a>' .
                    '</li>';
            }
            $lst .= ('' != $act ? '<li class="media-action">&nbsp;' . $act . '</li>' : '');

            // Show player if relevant
            if ('audio' == $file_type[0]) {
                $lst .= '<li>' . Media::audioPlayer($f->type, $f->file_url, null, null, false, false) . '</li>';
            }

            $res .= ('' != $lst ? '<ul>' . $lst . '</ul>' : '');
            $res .= '</div>';
        } else {
            $res = '<tr class="' . $class . '">';
            $res .= '<td class="media-action">' . $act . '</td>';
            $res .= '<td class="maximal" scope="row"><a class="media-flag media-link" href="' . rawurldecode($link) . '">' .
            '<img class="media-icon-square" src="' . $f->media_icon . '" alt="" />' . ($query ? $file : $fname) . '</a>' .
                '<br />' . ($f->d ? '' : ($f->media_priv ? '<img class="media-private" src="?df=images/locker.png" alt="' . __('private media') . '">' : '') . $f->media_title) . '</td>';
            $res .= '<td class="nowrap count">' . ($f->d ? '' : $f->media_dtstr) . '</td>';
            $res .= '<td class="nowrap count">' . ($f->d ? '' : Files::size($f->size) . ' - ' .
                '<a ' . $class_open . 'href="' . $f->file_url . '">' . __('open') . '</a>') . '</td>';
            $res .= '</tr>';
        }

        return $res;
    }
}
