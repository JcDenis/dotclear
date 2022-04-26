<?php
/**
 * @ingroup  Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
use Dotclear\App;

App::core()->help()->news('https://dotclear.org/blog/feed/category/News/atom');
App::core()->help()->doc([
    'Dotclear-dokumentaatio'     => 'https://dotclear.org/documentation/2.0',
    'Dotclearin esittely'        => 'https://dotclear.org/documentation/2.0/overview/tour',
    'Käyttäjän opas'             => 'https://dotclear.org/documentation/2.0/usage',
    'Asennus- ja hallintaoppaat' => 'https://dotclear.org/documentation/2.0/admin',
    'Dotclearin tukipalstat'     => 'https://forum.dotclear.net/',
]);
