<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

// Dotclear\Plugin\Tags\Common\TagsUrl
use Dotclear\App;
use Dotclear\Core\Url\Url;
use Dotclear\Database\Param;

/**
 * URL methods of plugin Tags.
 *
 * @ingroup  Plugin Tags Url
 */
class TagsUrl extends Url
{
    public function __construct()
    {
        App::core()->url()->register('tag', 'tag', '^tag/(.+)$', [$this, 'tag']);
        App::core()->url()->register('tags', 'tags', '^tags$', [$this, 'tags']);
        App::core()->url()->register('tag_feed', 'feed/tag', '^feed/tag/(.+)$', [$this, 'tagFeed']);
    }

    public function tag(?string $args): void
    {
        $n = App::core()->url()->getPageNumber($args);

        if ('' == $args && !$n) {
            App::core()->url()->p404();
        } elseif (preg_match('%(.*?)/feed/(rss2|atom)?$%u', $args, $m)) {
            $type     = 'atom' == $m[2] ? 'atom' : 'rss2';
            $mime     = 'application/xml';
            $comments = !empty($m[3]);

            $param = new Param();
            $param->set('meta_type', 'tag');
            $param->set('meta_id', $m[1]);

            App::core()->context()->set('meta', App::core()->meta()->computeMetaStats(
                App::core()->meta()->getMetadata(param: $param)
            ));

            if (App::core()->context()->get('meta')->isEmpty()) {
                App::core()->url()->p404();
            } else {
                $tpl = $type;

                if ('atom' == $type) {
                    $mime = 'application/atom+xml';
                }

                App::core()->url()->serveDocument($tpl . '.xml', $mime);
            }
        } else {
            if ($n) {
                App::core()->context()->page_number($n);
            }

            $param = new Param();
            $param->set('meta_type', 'tag');
            $param->set('meta_id', $args);

            App::core()->context()->set('meta', App::core()->meta()->computeMetaStats(
                App::core()->meta()->getMetadata(param: $param)
            ));

            if (App::core()->context()->get('meta')->isEmpty()) {
                App::core()->url()->p404();
            } else {
                App::core()->url()->serveDocument('tag.html');
            }
        }
    }

    public function tags(?string $args): void
    {
        App::core()->url()->serveDocument('tags.html');
    }

    public function tagFeed(?string $args): void
    {
        if (!preg_match('#^(.+)/(atom|rss2)(/comments)?$#', $args, $m)) {
            App::core()->url()->p404();
        } else {
            $tag      = $m[1];
            $type     = $m[2];
            $comments = !empty($m[3]);

            $param = new Param();
            $param->set('meta_type', 'tag');
            $param->set('meta_id', $tag);

            App::core()->context()->set('meta', App::core()->meta()->computeMetaStats(
                App::core()->meta()->getMetadata(param: $param)
            ));

            if (App::core()->context()->get('meta')->isEmpty()) {
                // The specified tag does not exist.
                App::core()->url()->p404();
            } else {
                App::core()->context()->set('feed_subtitle', ' - ' . __('Tag') . ' - ' . App::core()->context()->get('meta')->f('meta_id'));

                if ('atom' == $type) {
                    $mime = 'application/atom+xml';
                } else {
                    $mime = 'application/xml';
                }

                $tpl = $type;
                if ($comments) {
                    $tpl .= '-comments';
                    App::core()->context()->set('nb_comment_per_page', (int) App::core()->blog()->settings()->get('system')->get('nb_comment_per_feed'));
                } else {
                    App::core()->context()->set('nb_entry_per_page', (int) App::core()->blog()->settings()->get('system')->get('nb_post_per_feed'));
                    App::core()->context()->set('short_feed_items', (bool) App::core()->blog()->settings()->get('system')->get('short_feed_items'));
                }
                $tpl .= '.xml';

                App::core()->url()->serveDocument($tpl, $mime);
            }
        }
    }
}
