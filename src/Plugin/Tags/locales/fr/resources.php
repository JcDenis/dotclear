<?php
/**
 * @ingroup  PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('tags', __DIR__ . '/help/tags.html');
dotclear()->help()->context('tag_posts', __DIR__ . '/help/tag_posts.html');
dotclear()->help()->context('tag_post', __DIR__ . '/help/tag_post.html');
