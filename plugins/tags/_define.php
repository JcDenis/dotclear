<?php
/**
 * @file
 * @brief       The plugin tags definition
 * @ingroup     tags
 * 
 * @defgroup    tags Plugin tags.
 * 
 * tags, tags for posts.
 *
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
use Dotclear\App;

$this->registerModule(
    'Tags',            // Name
    'Tags for posts',  // Description
    'Olivier Meunier', // Author
    '2.0',             // Version
    [
        'permissions' => App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]),
        'priority' => 1001, // Must be higher than dcLegacyEditor/dcCKEditor priority (ie 1000)
        'type'     => 'plugin',
        'settings' => [
            'pref' => '#user-options.tags_prefs',
        ],
    ]
);
