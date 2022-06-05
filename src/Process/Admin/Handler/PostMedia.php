<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\PostMedia
use Dotclear\App;
use Dotclear\Core\Media\PostMedia as CoreMedia;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin post media selector helper page.
 *
 * @ingroup  Admin Post Media Handler
 */
class PostMedia extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $post_id   = GPC::request()->int('post_id', null);
        $media_id  = GPC::request()->int('media_id', null);
        $link_type = GPC::request()->string('link_type', null);

        if (!$post_id) {
            exit;
        }
        $param = new Param();
        $param->set('post_id', $post_id);
        $param->set('post_type', '');

        $rs = App::core()->blog()->posts()->getPosts(param: $param);
        if ($rs->isEmpty()) {
            exit;
        }

        try {
            $postmedia = new CoreMedia();

            if (null !== $post_id && null !== $media_id && !GPC::request()->empty('attach')) {
                $postmedia->addPostMedia($post_id, $media_id, $link_type);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-type: application/json');
                    echo json_encode(['url' => App::core()->posttype()->getPostAdminURL(type: $rs->f('post_type'), id: $post_id)]);

                    exit();
                }
                Http::redirect(App::core()->posttype()->getPostAdminURL(type: $rs->f('post_type'), id: $post_id));
            }

            $f = App::core()->media()->getPostMedia($post_id, $media_id, $link_type);
            if (empty($f)) {
                $post_id = $media_id = null;

                throw new AdminException(__('This attachment does not exist'));
            }
            $f = $f[0];
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        // Remove a media from en
        if (($post_id && $media_id) || App::core()->error()->flag()) {
            if (!GPC::post()->empty('remove')) {
                $postmedia->removePostMedia($post_id, $media_id, $link_type);

                App::core()->notice()->addSuccessNotice(__('Attachment has been successfully removed.'));
                Http::redirect(App::core()->posttype()->getPostAdminURL(type: $rs->f('post_type'), id: $post_id));
            } elseif (GPC::post()->isset('post_id')) {
                Http::redirect(App::core()->posttype()->getPostAdminURL(type: $rs->f('post_type'), id: $post_id));
            }

            if (!GPC::get()->empty('remove')) {
                $this
                    ->setPageTitle(__('Remove attachment'))
                    ->setPageBreadcrumb([
                        Html::escapeHTML(App::core()->blog()->name) => '',
                        __('Posts')                                 => '',
                    ])
                    ->setPageContent(
                        '<h2>' . __('Attachment') . ' &rsaquo; <span class="page-title">' . __('confirm removal') . '</span></h2>' .
                        '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
                        '<p>' . __('Are you sure you want to remove this attachment?') . '</p>' .
                        '<p><input type="submit" class="reset" value="' . __('Cancel') . '" /> ' .
                        ' &nbsp; <input type="submit" class="delete" name="remove" value="' . __('Yes') . '" />' .
                        Form::hidden('post_id', $post_id) .
                        Form::hidden('media_id', $media_id) .
                        App::core()->adminurl()->getHiddenFormFields('admin.post.media', [], true) . '</p>' .
                        '</form>'
                    )
                ;
            }

            return false;
        }

        return null;
    }
}
