<?php
/**
 * @brief Dotclear install core prepend class
 *
 * @package Dotclear
 * @subpackage Install
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Install;

use Dotclear\Core\Prepend as BasePrepend;
use Dotclear\Core\Utils;

use Dotclear\Utils\Http;
use Dotclear\Utils\L10n;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Install';

    public function __construct()
    {
        /* Serve a file (css, png, ...) */
        if (!empty($_GET['df'])) {
            Utils::fileServer([static::root('Admin', 'files')], 'df');
            exit;
        }

        /* Load parent (or part of) to get some constants */
        if (!defined('DOTCLEAR_CONFIG_PATH')) {
            parent::__construct();
        }

        /* No configuration ? start installalation process */
        if (!is_file(DOTCLEAR_CONFIG_PATH)) {
            new Wizard($this);
        } else {
            new Install($this);
        }
exit('install : inc/admin/install/xxx.php : structure only');
    }

    public static function systemCheck($con, &$err)
    {
        $err = [];

        if (version_compare(phpversion(), '7.4', '<')) {
            $err[] = sprintf(__('PHP version is %s (7.4 or earlier needed).'), phpversion());
        }

        if (!function_exists('mb_detect_encoding')) {
            $err[] = __('Multibyte string module (mbstring) is not available.');
        }

        if (!function_exists('iconv')) {
            $err[] = __('Iconv module is not available.');
        }

        if (!function_exists('ob_start')) {
            $err[] = __('Output control functions are not available.');
        }

        if (!function_exists('simplexml_load_string')) {
            $err[] = __('SimpleXML module is not available.');
        }

        if (!function_exists('dom_import_simplexml')) {
            $err[] = __('DOM XML module is not available.');
        }

        $pcre_str = base64_decode('w6nDqMOgw6o=');
        if (!@preg_match('/' . $pcre_str . '/u', $pcre_str)) {
            $err[] = __('PCRE engine does not support UTF-8 strings.');
        }

        if (!function_exists('spl_classes')) {
            $err[] = __('SPL module is not available.');
        }

        if ($con->syntax() == 'mysql') {
            if (version_compare($con->version(), '4.1', '<')) {
                $err[] = sprintf(__('MySQL version is %s (4.1 or earlier needed).'), $con->version());
            } else {
                $rs     = $con->select('SHOW ENGINES');
                $innodb = false;
                while ($rs->fetch()) {
                    if (strtolower($rs->f(0)) == 'innodb' && strtolower($rs->f(1)) != 'disabled' && strtolower($rs->f(1)) != 'no') {
                        $innodb = true;

                        break;
                    }
                }

                if (!$innodb) {
                    $err[] = __('MySQL InnoDB engine is not available.');
                }
            }
        } elseif ($con->driver() == 'pgsql') {
            if (version_compare($con->version(), '8.0', '<')) {
                $err[] = sprintf(__('PostgreSQL version is %s (8.0 or earlier needed).'), $con->version());
            }
        }

        return count($err) == 0;
    }
}
