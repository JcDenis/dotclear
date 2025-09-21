<?php

/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Process\Backend;

use Dotclear\App;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Span;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @since 2.27 Before as admin/post_media.php
 */
class PostMedia
{
    use TraitProcess;

    public static function init(): bool
    {
        App::backend()->page()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]));

        App::backend()->post_id   = empty($_REQUEST['post_id']) ? null : (int) $_REQUEST['post_id'];
        App::backend()->media_id  = empty($_REQUEST['media_id']) ? null : (int) $_REQUEST['media_id'];
        App::backend()->link_type = $_REQUEST['link_type'] ?? null;

        if (!App::backend()->post_id) {
            dotclear_exit();
        }

        return self::status(true);
    }

    public static function process(): bool
    {
        $rs = App::blog()->getPosts(['post_id' => App::backend()->post_id, 'post_type' => '']);
        if ($rs->isEmpty()) {
            dotclear_exit();
        }

        try {
            $pm = App::postMedia();

            if (App::backend()->media_id && !empty($_REQUEST['attach'])) {
                // Attach a media to an entry

                $pm->addPostMedia(App::backend()->post_id, App::backend()->media_id, App::backend()->link_type);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-type: application/json');
                    echo json_encode(['url' => App::postTypes()->get($rs->post_type)->adminUrl(App::backend()->post_id, false)], JSON_THROW_ON_ERROR);
                    dotclear_exit();
                }
                Http::redirect(App::postTypes()->get($rs->post_type)->adminUrl(App::backend()->post_id, false));
            }

            $f = App::media()->getPostMedia(App::backend()->post_id, App::backend()->media_id, App::backend()->link_type);
            if ($f === []) {
                App::backend()->post_id = App::backend()->media_id = null;

                throw new Exception(__('This attachment does not exist'));
            }
            $f = $f[0];
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        if ((App::backend()->post_id && App::backend()->media_id) || App::error()->flag()) {
            // Remove a media from entry

            if (!empty($_POST['remove'])) {
                $pm->removePostMedia(App::backend()->post_id, App::backend()->media_id, App::backend()->link_type);

                App::backend()->notices()->addSuccessNotice(__('Attachment has been successfully removed.'));
                Http::redirect(App::postTypes()->get($rs->post_type)->adminUrl(App::backend()->post_id, false));
            } elseif (isset($_POST['post_id'])) {
                Http::redirect(App::postTypes()->get($rs->post_type)->adminUrl(App::backend()->post_id, false));
            }

            if (!empty($_GET['remove'])) {
                App::backend()->page()->open(__('Remove attachment'));

                echo (new Text('h2', ' &rsaquo; ' . (new Span(__('confirm removal')))->class('page-title')->render()))
                ->render();

                echo
                (new Form())
                ->action(App::backend()->url()->get('admin.post.media'))
                ->method('post')
                ->fields([
                    (new Para())
                        ->items([
                            (new Text())->text(__('Are you sure you want to remove this attachment?')),
                        ]),
                    (new Para())
                        ->separator(' &nbsp; ')
                        ->items([
                            (new Submit('cancel'))->class('reset')->value(__('Cancel')),
                            (new Submit('remove'))->class('delete')->value(__('Yes')),
                        ]),
                    (new Hidden('post_id', (string) App::backend()->post_id)),
                    (new Hidden('media_id', (string) App::backend()->media_id)),
                    App::nonce()->formNonce(),
                ])
                ->render();

                App::backend()->page()->close();
                dotclear_exit();
            }
        }

        return true;
    }
}
