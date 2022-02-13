<?php
/**
 * @class Dotclear\Plugin\Attachments\Admin\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PlugniUserPref
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Admin;

use ArrayObject;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependAdmin;

use Dotclear\Admin\Page;
use Dotclear\Database\Record;
use Dotclear\File\Files;
use Dotclear\Html\Form;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Prepend extends AbstractPrepend
{
    use TraitPrependAdmin;

    public static function loadModule(): void
    {
        dotclear()->behavior()->add('adminPostFormItems', [__CLASS__, 'behaviorAdminPostFormItems']);
        dotclear()->behavior()->add('adminPostAfterForm', [__CLASS__, 'behaviorAdminPostAfterForm']);
        dotclear()->behavior()->add('adminPostHeaders', [__CLASS__, 'behaviorAdminPostHeaders']);
        dotclear()->behavior()->add('adminPageFormItems', [__CLASS__, 'behaviorAdminPostFormItems']);
        dotclear()->behavior()->add('adminPageAfterForm', [__CLASS__, 'behaviorAdminPostAfterForm']);
        dotclear()->behavior()->add('adminPageHeaders', [__CLASS__, 'behaviorAdminPostHeaders']);
        dotclear()->behavior()->add('adminPageHelpBlock', [__CLASS__, 'behaviorAadminPageHelpBlock']);
    }

    public static function behaviorAadminPageHelpBlock(ArrayObject $blocks): void
    {
        $found = false;
        foreach ($blocks as $block) {
            if ($block == 'core_post') {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }
        $blocks[] = 'attachments';
    }

    public static function behaviorAdminPostHeaders()
    {
        return Page::jsLoad('?mf=Plugin/Attachments/files/js/post.js');
    }

    public static function behaviorAdminPostFormItems(ArrayObject $main, ArrayObject $sidebar, ?Record $post): void
    {
        if ($post !== null) {
            $post_media = dotclear()->mediaInstance()->getPostMedia($post->post_id, null, 'attachment');
            $nb_media   = count($post_media);
            $title      = !$nb_media ? __('Attachments') : sprintf(__('Attachments (%d)'), $nb_media);
            $item       = '<h5 class="clear s-attachments">' . $title . '</h5>';
            foreach ($post_media as $f) {
                $ftitle = $f->media_title;
                if (strlen($ftitle) > 18) {
                    $ftitle = substr($ftitle, 0, 16) . '...';
                }
                $item .= '<div class="media-item s-attachments">' .
                '<a class="media-icon" href="' . dotclear()->adminurl->get('admin.media.item', ['id' => $f->media_id]) . '">' .
                '<img src="' . $f->media_icon . '" alt="" title="' . $f->basename . '" /></a>' .
                '<ul>' .
                '<li><a class="media-link" href="' . dotclear()->adminurl->get('admin.media.item', ['id' => $f->media_id]) . '" ' .
                'title="' . $f->basename . '">' . $ftitle . '</a></li>' .
                '<li>' . $f->media_dtstr . '</li>' .
                '<li>' . Files::size($f->size) . ' - ' .
                '<a href="' . $f->file_url . '">' . __('open') . '</a>' . '</li>' .

                '<li class="media-action"><a class="attachment-remove" id="attachment-' . $f->media_id . '" ' .
                'href="' . dotclear()->adminurl->get('admin.post.media', [
                    'post_id'   => $post->post_id,
                    'media_id'  => $f->media_id,
                    'link_type' => 'attachment',
                    'remove'    => '1'
                ]) . '">' .
                '<img src="?df=images/trash.png" alt="' . __('remove') . '" /></a>' .
                    '</li>' .

                    '</ul>' .
                    '</div>';
            }
            unset($f);

            if (empty($post_media)) {
                $item .= '<p class="form-note s-attachments">' . __('No attachment.') . '</p>';
            }
            $item .= '<p class="s-attachments"><a class="button" href="' . dotclear()->adminurl->get('admin.media', ['post_id' => $post->post_id, 'link_type' => 'attachment']) . '">' .
            __('Add files to this entry') . '</a></p>';
            $sidebar['metas-box']['items']['attachments'] = $item;
        }
    }

    public static function behaviorAdminPostAfterForm(?Record $post)
    {
        if ($post !== null) {
            echo
            '<form action="' . dotclear()->adminurl->get('admin.post.media') . '" id="attachment-remove-hide" method="post">' .
            '<div>' . form::hidden(['post_id'], $post->post_id) .
            form::hidden(['media_id'], '') .
            form::hidden(['link_type'], 'attachment') .
            form::hidden(['remove'], 1) .
            dotclear()->formNonce() . '</div></form>';
        }
    }
}
