<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Configuration;

// Dotclear\Core\Configuration\Configuration
use Dotclear\Helper\Configuration as ConfigurationHelper;
use Dotclear\Exception\InvalidConfiguration;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;

/**
 * Dotclear core configuration.
 *
 * @ingroup  Core Configuration
 */
final class Configuration extends ConfigurationHelper
{
    /**
     * Constructor.
     *
     * Try to read and parse dotclear configuration,
     * according to default configuration.
     *
     * @path null|string $path The path to configuration file
     */
    public function __construct(private ?string $path = null)
    {
        $path = is_null($this->path) || !is_file($this->path) ? [] : $this->path;

        parent::__construct($this->getDefaultConfiguration(), $path);

        // In non production environment, display all errors
        if ($this->get('production')) {
            ini_set('display_errors', '0');
        } else {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        }

        // Find a default appropriate language (used by Exceptions)
        foreach (Http::getAcceptLanguages() as $lang) {
            if ('en' == $lang || false !== L10n::set(Path::implode($this->get('l10n_dir'), $lang, 'main'))) {
                L10n::lang($lang);

                break;
            }
        }

        // Set some Http stuff
        Http::$https_scheme_on_443 = $this->get('force_scheme_443');
        Http::$reverse_proxy       = $this->get('reverse_proxy');
    }

    /**
     * Do advanced check for core configuration.
     *
     * @throws InvalidConfiguration
     */
    public function checkConfiguration(): void
    {
        // Check if configuration file exists
        if (!is_file($this->path)) {
            throw new InvalidConfiguration(
                    __('Application is not installed or configuration file is unreachabled.'),
                    500
            );
        }

        // Check master key
        if (32 > strlen($this->get('master_key'))) {
            throw new InvalidConfiguration(
                false === $this->get('production') ?
                    __('Master key is not strong enough, please change it.') :
                    __('Unsufficient master key')
            );
        }

        // Check cryptography algorithm
        if ('sha1' == $this->get('crypt_algo')) {
            // Check length of cryptographic algorithm result and exit if less than 40 characters long
            if (40 > strlen(Crypt::hmac($this->get('master_key'), $this->get('vendor_name'), $this->get('crypt_algo')))) {
                throw new InvalidConfiguration(
                    false === $this->get('production') ?
                        sprintf(__('%s cryptographic algorithm configured is not strong enough, please change it.'), $this->get('crypt_algo')) :
                        __('Cryptographic error')
                );
            }
        }

        // Check existence of digests directory
        if (!is_dir($this->get('digests_dir'))) {
            // Try to create it
            @Files::makeDir($this->get('digests_dir'));
        }

        // Check existence of cache directory
        if (!is_dir($this->get('cache_dir'))) {
            // Try to create it
            @Files::makeDir($this->get('cache_dir'));
            if (!is_dir($this->get('cache_dir'))) {
                throw new InvalidConfiguration(
                    false === $this->get('production') ?
                        sprintf(__('%s directory does not exist. Please create it.'), $this->get('cache_dir')) :
                        __('Unable to find cache directory')
                );
            }
        }

        // Check existence of var directory
        if (!is_dir($this->get('var_dir'))) {
            // Try to create it
            @Files::makeDir($this->get('var_dir'));
            if (!is_dir($this->get('var_dir'))) {
                throw new InvalidConfiguration(
                    false === $this->get('production') ?
                    sprintf('%s directory does not exist. Please create it.', $this->get('var_dir')) :
                    __('Unable to find var directory')
                );
            }
        }

        // Check configuration required values
        if ($this->error()->flag()) {
            throw new InvalidConfiguration(
                false === $this->get('production') ?
                    implode("\n", $this->error()->dump()) :
                    __('Configuration file is not complete.')
            );
        }
    }

    /**
     * Default Dotclear configuration.
     *
     * This configuration must be completed by
     * the dotclear.conf.php file.
     *
     * @return array<string,array> Initial configuation
     */
    private function getDefaultConfiguration(): array
    {
        return [
            'admin_adblocker_check' => [null, false],
            'admin_mailform'        => [null, ''],
            'admin_ssl'             => [null, true],
            'admin_url'             => [null, ''],
            'backup_dir'            => [null, Path::implodeBase()],
            'base_dir'              => [null, Path::implodeBase()],
            'cache_dir'             => [null, Path::implodeBase('cache')],
            'core_update_channel'   => [null, 'stable'],
            'core_update_noauto'    => [null, false],
            'core_update_url'       => [null, 'https://download.dotclear.org/versions.xml'],
            'core_version'          => [false, trim(file_get_contents(Path::implodeSrc('version')))],
            'core_version_break'    => [false, '3.0'],
            'crypt_algo'            => [null, 'sha1'],
            'database_driver'       => [true, ''],
            'database_host'         => [true, ''],
            'database_name'         => [true, ''],
            'database_password'     => [true, ''],
            'database_persist'      => [null, true],
            'database_prefix'       => [null, 'dc_'],
            'database_user'         => [true, ''],
            'digests_dir'           => [null, Path::implodeBase('digests')],
            'file_serve_type'       => [null, ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js', 'swf', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'html', 'xml', 'json', 'txt', 'zip']],
            'force_scheme_443'      => [null, true],
            'jquery_default'        => [null, '3.6.0'],
            'l10n_dir'              => [null, Path::implodeSrc('locales')],
            'l10n_update_url'       => [null, 'https://services.dotclear.net/dc2.l10n/?version=%s'],
            'media_dir_showhidden'  => [null, false],
            'media_upload_maxsize'  => [false, Files::getMaxUploadFilesize()],
            'master_key'            => [true, ''],
            'module_allow_multi'    => [null, false],
            'php_next_required'     => [false, '8.1'],
            'plugin_dirs'           => [null, [Path::implodeSrc('Plugin')]],
            'plugin_official'       => [false, ['AboutConfig', 'Akismet', 'Antispam', 'Attachments', 'Blogroll', 'Dclegacy', 'FairTrackbacks', 'ImportExport', 'Maintenance', 'Pages', 'Pings', 'SimpleMenu', 'Tags', 'ThemeEditor', 'UserPref', 'Widgets', 'LegacyEditor', 'CKEditor', 'Breadcrumb']],
            'plugin_update_url'     => [null,  'https://update.dotaddict.org/dc2/plugins.xml'],
            'production'            => [null, false],
            'query_timeout'         => [null, 4],
            'reverse_proxy'         => [null, true],
            'session_name'          => [null, 'dcxd'],
            'session_ttl'           => [null, '-120 minutes'],
            'sqlite_dir'            => [null, Path::implodeBase('db')],
            'store_allow_repo'      => [null, true],
            'store_update_noauto'   => [null, false],
            'template_default'      => [null, 'mustek'],
            'theme_default'         => [null, 'Berlin'],
            'theme_dirs'            => [null, [Path::implodeSrc('Theme')]],
            'theme_official'        => [false, ['Berlin', 'BlueSilence', 'Blowup', 'CustomCSS', 'Ductile']],
            'theme_update_url'      => [null, 'https://update.dotaddict.org/dc2/themes.xml'],
            'var_dir'               => [null, Path::implodeBase('var')],
            'vendor_name'           => [null, 'Dotclear'],
            'xmlrpc_url'            => [null, '%1$sxmlrpc/%2$s'],
        ];
    }
}
