<?php

/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
\Dotclear\App::backend()->resources()
    ->set('rss_news', 'Dotclear', 'https://dotclear.org/blog/feed/category/News/atom')
    ->set('doc', 'Dotclear documentation', 'https://dotclear.org/documentation/2.0')
    ->set('doc', 'Dotclear presentation', 'https://dotclear.org/documentation/2.0/overview/tour')
    ->set('doc', 'User manual', 'https://dotclear.org/documentation/2.0/usage')
    ->set('doc', 'Installation and administration guides', 'https://dotclear.org/documentation/2.0/admin')
    ->set('doc', 'Dotclear support forum', 'https://matrix.to/#/#dotclear:matrix.org');
