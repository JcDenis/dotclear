<?php
/**
 * @brief Dotclear constants default configuration
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

if (!defined('DOTCLEAR_ROOT_DIR')) {
    exit;
}

//*== DOTCLEAR_DEBUG ==
if (!defined('DOTCLEAR_DEBUG')) {
    define('DOTCLEAR_DEBUG', true);
}
if (DOTCLEAR_DEBUG) { // @phpstan-ignore-line
    ini_set('display_errors', '1');
    error_reporting(E_ALL | E_STRICT);
}
//*/

if (!defined('DOTCLEAR_DEBUG')) {
    define('DOTCLEAR_DEBUG', false);
}

define('DOTCLEAR_START_TIME',
    microtime(true)
);

define('DOTCLEAR_START_MEMORY',
    memory_get_usage(false)
);

define('DOTCLEAR_VERSION',
    '2.21-dev'
);

define('DOTCLEAR_DIGESTS_DIR',
    implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'digests'])
);

define('DOTCLEAR_L10N_DIR',
    implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'locales'])
);

define('DOTCLEAR_L10N_UPDATE_URL',
    'https://services.dotclear.net/dc2.l10n/?version=%s'
);

define('DOTCLEAR_DISTRIBUTED_PLUGINS',
    'aboutConfig,akismet,antispam,attachments,blogroll,blowupConfig,dclegacy,fairTrackbacks,importExport,maintenance,pages,pings,simpleMenu,tags,themeEditor,userPref,widgets,dcLegacyEditor,dcCKEditor,breadcrumb'
);

define('DOTCLEAR_DISTRIBUTED_THEMES',
    'berlin,blueSilence,blowupConfig,customCSS,default,ductile'
);

define('DOTCLEAR_DEFAULT_TEMPLATE_SET',
    'mustek'
);

define('DOTCLEAR_DEFAULT_JQUERY',
    '3.6.0'
);

if (!defined('DOTCLEAR_NEXT_REQUIRED_PHP')) {
    define('DOTCLEAR_NEXT_REQUIRED_PHP',
        '7.4'
    );
}

if (!defined('DOTCLEAR_VENDOR_NAME')) {
    define('DOTCLEAR_VENDOR_NAME',
        'Dotclear'
    );
}
if (!defined('DOTCLEAR_XMLRPC_URL')) {
    define('DOTCLEAR_XMLRPC_URL',
        '%1$sxmlrpc/%2$s'
    );
}

if (!defined('DOTCLEAR_SESSION_TTL')) {
    define('DOTCLEAR_SESSION_TTL',
        null
    );
}

if (!defined('DOTCLEAR_ADMIN_SSL')) {
    define('DOTCLEAR_ADMIN_SSL',
        true
    );
}

if (!defined('DOTCLEAR_FORCE_SCHEME_443')) {
    define('DOTCLEAR_FORCE_SCHEME_443',
        true
    );
}

if (!defined('DOTCLEAR_REVERSE_PROXY')) {
    define('DOTCLEAR_REVERSE_PROXY',
        true
    );
}

if (!defined('DOTCLEAR_DATABASE_PERSIST')) {
    define('DOTCLEAR_DBPERSIST',
        false
    );
}

if (!defined('DOTCLEAR_SESSION_NAME')) {
    define('DOTCLEAR_SESSION_NAME',
        'dcxd'
    );
}

if (!defined('DOTCLEAR_UPDATE_URL')) {
    define('DOTCLEAR_UPDATE_URL',
        'https://download.dotclear.org/versions.xml'
    );
}

if (!defined('DOTCLEAR_UPDATE_VERSION')) {
    define('DOTCLEAR_UPDATE_VERSION',
        'stable'
    );
}

if (!defined('DOTCLEAR_NOT_UPDATE')) {
    define('DOTCLEAR_NOT_UPDATE',
        false
    );
}

if (!defined('DOTCLEAR_ALLOW_MULTI_MODULES')) {
    define('DOTCLEAR_ALLOW_MULTI_MODULES',
        false
    );
}

if (!defined('DOTCLEAR_STORE_NOT_UPDATE')) {
    define('DOTCLEAR_STORE_NOT_UPDATE',
        false
    );
}

if (!defined('DOTCLEAR_ALLOW_REPOSITORIES')) {
    define('DOTCLEAR_ALLOW_REPOSITORIES',
        true
    );
}

if (!defined('DOTCLEAR_QUERY_TIMEOUT')) {
    define('DOTCLEAR_QUERY_TIMEOUT',
        4
    );
}

if (!defined('DOTCLEAR_CRYPT_ALGO')) {
    define('DOTCLEAR_CRYPT_ALGO',
        'sha1'
    );
}

if (!defined('DOTCLEAR_CACHE_DIR')) {
    define('DOTCLEAR_CACHE_DIR',
        implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, '..', 'cache'])
    );
}

if (!defined('DOTCLEAR_VAR_DIR')) {
    define('DOTCLEAR_VAR_DIR',
        implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, '..', 'var'])
    );
}
