<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear;

use Autoloader;
use Dotclear\Helper\Date;
use Dotclear\Helper\L10n;

/**
 * Application.
 */
final class App
{
    private static ?\Autoloader $autoload = null;

    /**
     * Call Dotclear autoloader.
     *
     * @return Autoloader $autoload The autoload instance
     */
    public static function autoload(): Autoloader
    {
        if (!self::$autoload) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'Autoloader.php';
            self::$autoload = new Autoloader('', '', true);
        }

        return self::$autoload;
    }

    /**
     * Initializes the object.
     */
    public static function init(): void
    {
        // We may need l10n __() function
        L10n::bootstrap();

        mb_internal_encoding('UTF-8');

        # Setting timezone
        Date::setTZ('UTC');

        # CLI_MODE, boolean constant that tell if we are in CLI mode
        define('CLI_MODE', PHP_SAPI == 'cli');

        # Disallow every special wrapper
        (function () {
            if (function_exists('stream_wrapper_unregister')) {
                $special_wrappers = array_intersect([
                    'http',
                    'https',
                    'ftp',
                    'ftps',
                    'ssh2.shell',
                    'ssh2.exec',
                    'ssh2.tunnel',
                    'ssh2.sftp',
                    'ssh2.scp',
                    'ogg',
                    'expect',
                    // 'phar',   // Used by PharData to manage Zip/Tar archive
                ], stream_get_wrappers());
                foreach ($special_wrappers as $p) {
                    @stream_wrapper_unregister($p);
                }
                unset($special_wrappers, $p);
            }
        })();

        if (isset($_SERVER['DC_RC_PATH'])) {
            define('DC_RC_PATH', $_SERVER['DC_RC_PATH']);
        } elseif (isset($_SERVER['REDIRECT_DC_RC_PATH'])) {
            define('DC_RC_PATH', $_SERVER['REDIRECT_DC_RC_PATH']);
        } else {
            define('DC_RC_PATH', implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'inc', 'config.php']));
        }
    }
}
