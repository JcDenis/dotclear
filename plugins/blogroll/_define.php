<?php
/**
 * @file
 * @brief       The plugin blogroll definition
 * @ingroup     blogroll
 *
 * @defgroup    blogroll Plugin blogroll.
 *
 * blogroll, manage your blogroll plugin for Dotclear.
 *
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
$this->registerModule(
    'Blogroll',             // Name
    'Manage your blogroll', // Description
    'Olivier Meunier',      // Author
    '2.0',                  // Version
    [
        'permissions' => 'My',
        'type'        => 'plugin',
    ]
);
