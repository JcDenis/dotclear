<?php
/**
 * @note Dotclear\Plugin\Attachments\Admin\AttachmentsBehavior
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginAttachments
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Attachments\Admin;

use ArrayObject;
use Dotclear\Database\Record;
use Dotclear\Helper\File\Files;

class AttachmentsBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('adminPostFormItems', [$this, 'behaviorAdminPostFormItems']);
        dotclear()->behavior()->add('adminPostAfterForm', [$this, 'behaviorAdminPostAfterForm']);
        dotclear()->behavior()->add('adminPostHeaders', [$this, 'behaviorAdminPostHeaders']);
        dotclear()->behavior()->add('adminPageFormItems', [$this, 'behaviorAdminPostFormItems']);
        dotclear()->behavior()->add('adminPageAfterForm', [$this, 'behaviorAdminPostAfterForm']);
        dotclear()->behavior()->add('adminPageHeaders', [$this, 'behaviorAdminPostHeaders']);
        dotclear()->behavior()->add('adminPageHelpBlock', [$this, 'behaviorAdminPageHelpBlock']);
    }

    public function behaviorAdminPageHelpBlock(ArrayObject $blocks): void
    {
        $found = false;
        foreach ($blocks as $block) {
            if ('core_post' == $block) {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }
        $blocks[] = 'attachments';
    }

    public function behaviorAdminPostHeaders()
    {
        return dotclear()->resource()->load('post.js', 'Plugin', 'Attachments');
    }

    public function behaviorAdminPostFormItems(ArrayObject $main, ArrayObject $sidebar, ?Record $post): void
    {
        if (null !== $post && dotclear()->media()) {
            $post_media = dotclear()->media()->getPostMedia($post->fInt('post_id'), null, 'attachment');
            $nb_media   = count($post_media);
            $title      = !$nb_media ? __('Attachments') : sprintf(__('Attachments (%d)'), $nb_media);
            $item       = '<h5 class="clear s-attachments">' . $title . '</h5>';
            foreach ($post_media as $f) {
                $ftitle = $f->media_title;
                if (18 < strlen($ftitle)) {
                    $ftitle = substr($ftitle, 0, 16) . '...';
                }
                $item .= '<div class="media-item s-attachments">' .
                '<a class="media-icon" href="' . dotclear()->adminurl()->get('admin.media.item', ['id' => $f->media_id]) . '">' .
                '<img src="' . $f->media_icon . '" alt="" title="' . $f->basename . '" /></a>' .
                '<ul>' .
                '<li><a class="media-link" href="' . dotclear()->adminurl()->get('admin.media.item', ['id' => $f->media_id]) . '" ' .
                'title="' . $f->basename . '">' . $ftitle . '</a></li>' .
                '<li>' . $f->media_dtstr . '</li>' .
                '<li>' . Files::size($f->size) . ' - ' .
                '<a href="' . $f->file_url . '">' . __('open') . '</a>' . '</li>' .

                '<li class="media-action"><a class="attachment-remove" id="attachment-' . $f->media_id . '" ' .
                'href="' . dotclear()->adminurl()->get('admin.post.media', [
                    'post_id'   => $post->f('post_id'),
                    'media_id'  => $f->media_id,
                    'link_type' => 'attachment',
                    'remove'    => '1',
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
            $item .= '<p class="s-attachments"><a class="button" href="' . dotclear()->adminurl()->get('admin.media', ['post_id' => $post->f('post_id'), 'link_type' => 'attachment']) . '">' .
            __('Add files to this entry') . '</a></p>';
            $sidebar['metas-box']['items']['attachments'] = $item;
        }
    }

    public function behaviorAdminPostAfterForm(?Record $post)
    {
        if (null !== $post) {
            echo '<form action="' . dotclear()->adminurl()->root() . '" id="attachment-remove-hide" method="post">' .
            '<div>' .
            dotclear()->adminurl()->getHiddenFormFields('admin.post.media', [
                'post_id'   => $post->f('post_id'),
                'media_id'  => '',
                'link_type' => 'attachement',
                'remove'    => 1,
            ], true) .
            '</div></form>';
        }
    }
}
