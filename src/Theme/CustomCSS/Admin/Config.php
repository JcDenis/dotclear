<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Theme\CustomCSS\Admin;

// Dotclear\Theme\CustomCSS\Admin\Config
use Dotclear\App;
use Dotclear\Exception\ModuleException;
use Dotclear\Module\AbstractConfig;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Dotclear\Helper\File\Path;

/**
 * Admin config page for theme CustomCSS.
 *
 * @ingroup  Theme CustomCSS
 */
class Config extends AbstractConfig
{
    private $customcss_file = '';

    public function getPermissions(): ?string
    {
        return 'admin';
    }

    public function setConfiguration(array $post): void
    {
        $this->customcssConf();

        if (isset($post['css'])) {
            @$fp = fopen($this->customcss_file, 'wb');
            fwrite($fp, $post['css']);
            fclose($fp);

            App::core()->notice()->addSuccessNotice(__('Style sheet upgraded.'));
            $this->redirect();
        }
    }

    public function getConfiguration(): void
    {
        $this->customcssConf();

        echo '<p class="area"><label>' . __('Style sheet:') . '</label> ' .
        Form::textarea('css', 60, 20, Html::escapeHTML(is_file($this->customcss_file) ? file_get_contents($this->customcss_file) : '')) . '</p>';
    }

    private function customcssConf()
    {
        L10n::set(Path::implode(__DIR__, '..', 'locales', App::core()->lang(), 'main'));
        $this->customcss_file = Path::real(App::core()->blog()->public_path) . '/custom_style.css';

        if (!is_file($this->customcss_file) && !is_writable(dirname($this->customcss_file))) {
            throw new ModuleException(
                sprintf(
                    __('File %s does not exist and directory %s is not writable.'),
                    $this->customcss_file,
                    dirname($this->customcss_file)
                )
            );
        }
    }
}
