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
use Dotclear\Utils\Http;
use Dotclear\Utils\L10n;

class Prepend extends BasePrepend
{
    protected $process = 'Install';

    public function __construct()
    {
        /* No configuration ? start installalation process */
        if (!is_file(DOTCLEAR_CONFIG_PATH)) {
            $this->wizard();
        } else {
            parent::__construct();
            $this->install();
        }
echo 'install : inc/admin/install/xxx.php : structure only ';
    }

    protected function install()
    {
        $can_install = true;
        $err         = '';

        # Loading locales for detected language
        $dlang = Http::getAcceptLanguage();
        if ($dlang != 'en') {
            L10n::init($dlang);
            L10n::set(static::root(DOTCLEAR_L10N_DIR, $dlang, 'date'));
            L10n::set(static::root(DOTCLEAR_L10N_DIR, $dlang, 'main'));
            L10n::set(static::root(DOTCLEAR_L10N_DIR, $dlang, 'plugins'));
        }

        if (!defined('DOTCLEAR_MASTER_KEY') || DOTCLEAR_MASTER_KEY == '') {
            $can_install = false;
            $err         = '<p>' . __('Please set a master key (DOTCLEAR_MASTER_KEY) in configuration file.') . '</p>';
        }
echo 'install: index.php : structure only ';
    }

    protected function wizard()
    {
        # Loading locales for detected language
        $dlang = Http::getAcceptLanguage();
        if ($dlang != 'en') {
            L10n::init($dlang);
            L10n::set(static::root(DOTCLEAR_L10N_DIR, $dlang, 'main'));
        }
echo 'install: wizard.php : structure only ';
    }
}
