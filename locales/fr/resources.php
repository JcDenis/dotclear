<?php
/**
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright AGPL-3.0
 */
\Dotclear\App::backend()->resources()
    ->reset('rss_news') // remove previously set "en" rss news
    ->set('rss_news', 'Dotclear', 'https://fr.dotclear.org/blog/feed/category/News/atom')
    ->reset('doc') // remove previously set "en" doc
    ->set('doc', "Accueil de l'aide Dotclear", 'https://fr.dotclear.org/documentation/2.0')
    ->set('doc', 'Présentation de Dotclear', 'https://fr.dotclear.org/documentation/2.0/overview/tour')
    ->set('doc', "Manuel de l'utilisateur", 'https://fr.dotclear.org/documentation/2.0/usage')
    ->set('doc', "Guide d'installation et d'administration", 'https://fr.dotclear.org/documentation/2.0/admin')
    ->set('doc', 'Forum de support de Dotclear', 'https://forum.dotclear.net/');
