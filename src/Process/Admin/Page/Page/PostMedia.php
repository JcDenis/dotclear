<?php
/**
 * @class Dotclear\Process\Admin\Page\Page\PostMedia
 * @brief Dotclear admin post media selector helper page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Page\Page;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Core\Media\PostMedia;
use Dotclear\Exception\AdminException;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class PostMedia extends Page
{
    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $post_id   = !empty($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : null;
        $media_id  = !empty($_REQUEST['media_id']) ? (int) $_REQUEST['media_id'] : null;
        $link_type = !empty($_REQUEST['link_type']) ? $_REQUEST['link_type'] : null;

        if (!$post_id) {
            exit;
        }
        $rs = dotclear()->blog()->posts()->getPosts(['post_id' => $post_id, 'post_type' => '']);
        if ($rs->isEmpty()) {
            exit;
        }

        try {
            $postmedia = new PostMedia();

            if ($post_id && $media_id && !empty($_REQUEST['attach'])) { // @phpstan-ignore-line
                $postmedia->addPostMedia($post_id, $media_id, $link_type);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-type: application/json');
                    echo json_encode(['url' => dotclear()->posttype()->getPostAdminURL($rs->post_type, $post_id, false)]);
                    exit();
                }
                Http::redirect(dotclear()->posttype()->getPostAdminURL($rs->post_type, $post_id, false));
            }

            $f = dotclear()->media()->getPostMedia($post_id, $media_id, $link_type);
            if (empty($f)) {
                $post_id = $media_id = null;

                throw new AdminException(__('This attachment does not exist'));
            }
            $f = $f[0];
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        # Remove a media from en
        if (($post_id && $media_id) || dotclear()->error()->flag()) {
            if (!empty($_POST['remove'])) {
                $postmedia->removePostMedia($post_id, $media_id, $link_type);

                dotclear()->notice()->addSuccessNotice(__('Attachment has been successfully removed.'));
                Http::redirect(dotclear()->posttype()->getPostAdminURL($rs->post_type, $post_id, false));
            } elseif (isset($_POST['post_id'])) {
                Http::redirect(dotclear()->posttype()->getPostAdminURL($rs->post_type, $post_id, false));
            }

            if (!empty($_GET['remove'])) {
                $this
                    ->setPageTitle(__('Remove attachment'))
                    ->setPageBreadcrumb([
                        Html::escapeHTML(dotclear()->blog()->name) => '',
                        __('Posts')                            => ''
                    ])
                    ->setPageContent(
                        '<h2>' . __('Attachment') . ' &rsaquo; <span class="page-title">' . __('confirm removal') . '</span></h2>' .
                        '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
                        '<p>' . __('Are you sure you want to remove this attachment?') . '</p>' .
                        '<p><input type="submit" class="reset" value="' . __('Cancel') . '" /> ' .
                        ' &nbsp; <input type="submit" class="delete" name="remove" value="' . __('Yes') . '" />' .
                        Form::hidden('post_id', $post_id) .
                        Form::hidden('media_id', $media_id) .
                        dotclear()->adminurl()->getHiddenFormFields('admin.post.media', [], true) . '</p>' .
                        '</form>'
                    )
                ;
            }

            return false;
        }
    }
}