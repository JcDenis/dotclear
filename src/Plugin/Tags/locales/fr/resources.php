<?php
/**
 * @ingroup  PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->context('tags', __DIR__ . '/help/tags.html');
App::core()->help()->context('tag_posts', __DIR__ . '/help/tag_posts.html');
App::core()->help()->context('tag_post', __DIR__ . '/help/tag_post.html');
