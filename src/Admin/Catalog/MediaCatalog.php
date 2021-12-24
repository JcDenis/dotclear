<?php
/**
 * @class Dotclear\Admin\Catalog\MediaCatalog
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
use Dotclear\Core\Media;

use Dotclear\Admin\Pager;
use Dotclear\Admin\Catalog;

use Dotclear\Html\Html;
use Dotclear\Html\Form;
use Dotclear\File\Files;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class MediaCatalog extends Catalog
{
    /**
     * Display a media list
     *
     * @param      adminMediaFilter     $filters        The filters
     * @param      string               $enclose_block  The enclose block
     */
    public function display($filters, $enclose_block = '', $query = false, $page_adminurl = 'admin.media')
    {
        $nb_items   = $this->rs_count - ($filters->d ? 1 : 0);
        $nb_folders = $filters->d ? -1 : 0;

        if ($filters->q && !$query) {
            echo '<p><strong>' . __('No file matches the filter') . '</strong></p>';
        } elseif ($nb_items < 1) {
            echo '<p><strong>' . __('No file.') . '</strong></p>';
        }

        if ($this->rs_count && !($filters->q && !$query)) {
            $pager = new Pager($filters->page, $this->rs_count, $filters->nb, 10);

            $items = $this->rs->rows();
            foreach ($items as $item) {
                if ($item->d) {
                    $nb_folders++;
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
                $group[$items[$i]->d ? 'dirs' : 'files'][] = $this->mediaLine($this->core, $filters, $items[$i], $j, $query, $page_adminurl);
            }

            if ($filters->file_mode == 'list') {
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

            echo $pager->getLinks();

            echo $html_block;

            echo $pager->getLinks();
        }
    }

    public static function mediaLine($core, $filters, $f, $i, $query = false, $page_adminurl = 'admin.media')
    {
        $fname = $f->basename;
        $file  = $query ? $f->relname : $f->basename;

        $class = 'media-item-bloc'; // cope with js message for grid AND list
        $class .= $filters->file_mode == 'list' ? '' : ' media-item media-col-' . ($i % 2);

        if ($f->d) {
            // Folder
            $link = $core->adminurl->get('admin.media', array_merge($filters->values(), ['d' => Html::sanitizeURL($f->relname)]));
            if ($f->parent) {
                $fname = '..';
                $class .= ' media-folder-up';
            } else {
                $class .= ' media-folder';
            }
        } else {
            // Item
            $params = new \ArrayObject(array_merge($filters->values(), ['id' => $f->media_id]));

            $core->callBehavior('adminMediaURLParams', $params);

            $link = $core->adminurl->get('admin.media.item', (array) $params);
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
            if ($filters->select > 0) {
                if ($filters->select == 1) {
                    // Single media selection button
                    $act .= '<a href="' . $link . '"><img src="?df=images/plus.png" alt="' . __('Select this file') . '" ' .
                    'title="' . __('Select this file') . '" /></a> ';
                } else {
                    // Multiple media selection checkbox
                    $act .= Form::checkbox(['medias[]', 'media_' . rawurlencode($file)], $file);
                }
            } else {
                // Item
                if ($filters->post_id) {
                    // Media attachment button
                    $act .= '<a class="attach-media" title="' . __('Attach this file to entry') . '" href="' .
                    $core->adminurl->get(
                        'admin.post.media',
                        ['media_id' => $f->media_id, 'post_id' => $filters->post_id, 'attach' => 1, 'link_type' => $filters->link_type]
                    ) .
                    '">' .
                    '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '"/>' .
                        '</a>';
                }
                if ($filters->popup) {
                    // Media insertion button
                    $act .= '<a href="' . $link . '"><img src="?df=images/plus.png" alt="' . __('Insert this file into entry') . '" ' .
                    'title="' . __('Insert this file into entry') . '" /></a> ';
                }
            }
        }
        if ($f->del) {
            // Deletion button or checkbox
            if (!$filters->popup && !$f->d) {
                if ($filters->select < 2) {
                    // Already set for multiple media selection
                    $act .= Form::checkbox(['medias[]', 'media_' . rawurlencode($file)], $file);
                }
            } else {
                $act .= '<a class="media-remove" ' .
                'href="' . $core->adminurl->get($page_adminurl, array_merge($filters->values(), ['remove' => rawurlencode($file)])) . '">' .
                '<img src="?df=images/trash.png" alt="' . __('Delete') . '" title="' . __('delete') . '" /></a>';
            }
        }

        $file_type  = explode('/', (string) $f->type);
        $class_open = 'class="modal-' . $file_type[0] . '" ';

        // Render markup
        if ($filters->file_mode != 'list') {
            $res = '<div class="' . $class . '"><p><a class="media-icon media-link" href="' . rawurldecode($link) . '">' .
            '<img src="' . $f->media_icon . '" alt="" />' . ($query ? $file : $fname) . '</a></p>';

            $lst = '';
            if (!$f->d) {
                $lst .= '<li>' . ($f->media_priv ? '<img class="media-private" src="?df=images/locker.png" alt="' . __('private media') . '">' : '') . $f->media_title . '</li>' .
                '<li>' .
                $f->media_dtstr . ' - ' .
                Files::size($f->size) . ' - ' .
                '<a ' . $class_open . 'href="' . $f->file_url . '">' . __('open') . '</a>' .
                    '</li>';
            }
            $lst .= ($act != '' ? '<li class="media-action">&nbsp;' . $act . '</li>' : '');

            // Show player if relevant
            if ($file_type[0] == 'audio') {
                $lst .= '<li>' . Media::audioPlayer($f->type, $f->file_url, null, null, false, false) . '</li>';
            }

            $res .= ($lst != '' ? '<ul>' . $lst . '</ul>' : '');
            $res .= '</div>';
        } else {
            $res = '<tr class="' . $class . '">';
            $res .= '<td class="media-action">' . $act . '</td>';
            $res .= '<td class="maximal" scope="row"><a class="media-flag media-link" href="' . rawurldecode($link) . '">' .
            '<img src="' . $f->media_icon . '" alt="" />' . ($query ? $file : $fname) . '</a>' .
                '<br />' . ($f->d ? '' : ($f->media_priv ? '<img class="media-private" src="?df=images/locker.png" alt="' . __('private media') . '">' : '') . $f->media_title) . '</td>';
            $res .= '<td class="nowrap count">' . ($f->d ? '' : $f->media_dtstr) . '</td>';
            $res .= '<td class="nowrap count">' . ($f->d ? '' : Files::size($f->size) . ' - ' .
                '<a ' . $class_open . 'href="' . $f->file_url . '">' . __('open') . '</a>') . '</td>';
            $res .= '</tr>';
        }

        return $res;
    }
}
