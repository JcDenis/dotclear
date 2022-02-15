<?php
/**
 * @class Dotclear\Theme\CustomCSS\Admin\Config
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage ThemeCustomCSS
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\CustomCSS\Admin;

use Dotclear\Exception\ModuleException;

use Dotclear\Module\AbstractConfig;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Utils\L10n;
use Dotclear\File\Path;


if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Config extends AbstractConfig
{
    private $customcss_file = '';

    public static function getPermissions(): ?string
    {
        return 'admin';
    }

    public function setConfiguration(array $post, string $redir): void
    {
        $this->customcssConf();

        if (isset($post['css'])) {
            @$fp = fopen($this->customcss_file, 'wb');
            fwrite($fp, $post['css']);
            fclose($fp);

            dotclear()->notice()->addSuccessNotice(__('Style sheet upgraded.'));
            Http::redirect($redir);
        }
    }

    public function getConfiguration(): void
    {
        $this->customcssConf();

        echo
        '<p class="area"><label>' . __('Style sheet:') . '</label> ' .
        Form::textarea('css', 60, 20, Html::escapeHTML(is_file($this->customcss_file) ? file_get_contents($this->customcss_file) : '')) . '</p>';
    }

    private function customcssConf()
    {
        L10n::set(implode_path(__DIR__, '..',  'locales', dotclear()->_lang, 'main'));
        $this->customcss_file = Path::real(dotclear()->blog()->public_path) . '/custom_style.css';

        if (!is_file($this->customcss_file) && !is_writable(dirname($this->customcss_file))) {
            throw new ModuleException(
                sprintf(__('File %s does not exist and directory %s is not writable.'),
                    $this->customcss_file, dirname($this->customcss_file))
            );
        }
    }
}
