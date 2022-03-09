<?php
/**
 * @class Dotclear\Plugin\Tags\Common\TagsUrl
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Common;

use Dotclear\Core\Url\Url;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class TagsUrl extends Url
{
    public function __construct()
    {
        dotclear()->url()->register('tag', 'tag', '^tag/(.+)$', [$this, 'tag']);
        dotclear()->url()->register('tags', 'tags', '^tags$', [$this, 'tags']);
        dotclear()->url()->register('tag_feed', 'feed/tag', '^feed/tag/(.+)$', [$this, 'tagFeed']);
    }

    public function tag($args)
    {
        $n = dotclear()->url()->getPageNumber($args);

        if ($args == '' && !$n) {
            dotclear()->url()->p404();
        } elseif (preg_match('%(.*?)/feed/(rss2|atom)?$%u', $args, $m)) {
            $type     = $m[2] == 'atom' ? 'atom' : 'rss2';
            $mime     = 'application/xml';
            $comments = !empty($m[3]);

            dotclear()->context()->meta = dotclear()->meta()->computeMetaStats(
                dotclear()->meta()->getMetadata([
                    'meta_type' => 'tag',
                    'meta_id'   => $m[1], ])
            );

            if (dotclear()->context()->meta->isEmpty()) {
                dotclear()->url()->p404();
            } else {
                $tpl = $type;

                if ($type == 'atom') {
                    $mime = 'application/atom+xml';
                }

                dotclear()->url()->serveDocument($tpl . '.xml', $mime);
            }
        } else {
            if ($n) {
                dotclear()->context()->page_number($n);
            }

            dotclear()->context()->meta = dotclear()->meta()->computeMetaStats(
                dotclear()->meta()->getMetadata([
                    'meta_type' => 'tag',
                    'meta_id'   => $args, ])
            );

            if (dotclear()->context()->meta->isEmpty()) {
                dotclear()->url()->p404();
            } else {
                dotclear()->url()->serveDocument('tag.html');
            }
        }
    }

    public function tags($args)
    {
        dotclear()->url()->serveDocument('tags.html');
    }

    public function tagFeed($args)
    {
        if (!preg_match('#^(.+)/(atom|rss2)(/comments)?$#', $args, $m)) {
            dotclear()->url()->p404();
        } else {
            $tag      = $m[1];
            $type     = $m[2];
            $comments = !empty($m[3]);

            dotclear()->context()->meta = dotclear()->meta()->computeMetaStats(
                dotclear()->meta()->getMetadata([
                    'meta_type' => 'tag',
                    'meta_id'   => $tag, ])
            );

            if (dotclear()->context()->meta->isEmpty()) {
                # The specified tag does not exist.
                dotclear()->url()->p404();
            } else {
                dotclear()->context()->feed_subtitle = ' - ' . __('Tag') . ' - ' . dotclear()->context()->meta->meta_id;

                if ($type == 'atom') {
                    $mime = 'application/atom+xml';
                } else {
                    $mime = 'application/xml';
                }

                $tpl = $type;
                if ($comments) {
                    $tpl .= '-comments';
                    dotclear()->context()->nb_comment_per_page = dotclear()->blog()->settings()->system->nb_comment_per_feed;
                } else {
                    dotclear()->context()->nb_entry_per_page = dotclear()->blog()->settings()->system->nb_post_per_feed;
                    dotclear()->context()->short_feed_items  = dotclear()->blog()->settings()->system->short_feed_items;
                }
                $tpl .= '.xml';

                dotclear()->url()->serveDocument($tpl, $mime);
            }
        }
    }
}
