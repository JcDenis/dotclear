<?php
/**
 * @brief Repository modules XML feed reader
 *
 * Provides an object to parse XML feed of modules from repository.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.6
 */

use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Network\HttpClient;

class dcStore
{
    /**
     * dcModules instance
     *
     * @var    object
     */
    public $modules;

    /**
     * Modules fields to search on and their weight
     *
     * @var    array
     */
    public static $weighting = [
        'id'     => 10,
        'name'   => 8,
        'tags'   => 6,
        'desc'   => 4,
        'author' => 2,
    ];

    /**
     * User agent used to query repository
     *
     * @var    string
     */
    protected $user_agent = 'DotClear.org RepoBrowser/0.1';

    /**
     * XML feed URL
     *
     * @var    string
     */
    protected $xml_url;

    /**
     * Array of new/update modules from repository
     *
     * @var    array
     */
    protected $data = [
        'new'    => [],
        'update' => [],
    ];

    /**
     * Array of new/update modules Define from repository
     *
     * @var    array
     */
    protected $defines = [
        'new'    => [],
        'update' => [],
    ];

    /**
     * Repositories new updates status.
     *
     * @var     bool
     */
    private bool $has_new_update = false;

    /**
     * Constructor.
     *
     * @param    dcModules      $modules        dcModules instance
     * @param    string         $xml_url        XML feed URL
     * @param    null|bool      $force          Force query repository
     */
    public function __construct(dcModules $modules, string $xml_url, ?bool $force = false)
    {
        $this->modules    = $modules;
        $this->xml_url    = $xml_url;
        $this->user_agent = sprintf('Dotclear/%s)', DC_VERSION);

        $this->check($force);
    }

    /**
     * Check repository.
     *
     * @param    bool    $force        Force query repository
     *
     * @return    bool    True if get feed or cache
     */
    public function check(?bool $force = false): bool
    {
        if (!$this->xml_url) {
            return false;
        }

        try {
            $str_parser = DC_STORE_NOT_UPDATE ? false : dcStoreReader::quickParse($this->xml_url, DC_TPL_CACHE, $force);
        } catch (Exception $e) {
            return false;
        }

        $new_defines  = [];
        $upd_defines  = [];
        $upd_versions = [];

        // check update/new from main repository
        if ($str_parser !== false) {
            foreach ($str_parser->getDefines() as $str_define) {
                // is installed ?
                $cur_define = $this->modules->getDefine($str_define->getId());
                if ($cur_define->isDefined()) {
                    // is update ?
                    if (dcUtils::versionsCompare($str_define->get('version'), $cur_define->get('version'), '>')) {
                        $str_define->set('root', $cur_define->get('root'));
                        $str_define->set('root_writable', $cur_define->get('root_writable'));
                        $str_define->set('current_version', $cur_define->get('version'));

                        // set memo for third party updates
                        $upd_versions[$str_define->getId()] = [count($upd_defines), $str_define->get('version')];

                        $upd_defines[] = $str_define;

                        // This update is new from main repository
                        if (dcStoreReader::readCode() === dcStoreReader::READ_FROM_SOURCE) {
                            $this->has_new_update = true;
                        }
                    }
                    // it's new
                } else {
                    $new_defines[] = $str_define;
                }
            }
        }

        // check update from third party repositories
        foreach ($this->modules->getDefines() as $cur_define) {
            if ($cur_define->get('repository') != '' && DC_ALLOW_REPOSITORIES) {
                try {
                    $str_url    = substr($cur_define->get('repository'), -12, 12) == '/dcstore.xml' ? $cur_define->get('repository') : Http::concatURL($cur_define->get('repository'), 'dcstore.xml');
                    $str_parser = dcStoreReader::quickParse($str_url, DC_TPL_CACHE, $force);
                    if ($str_parser === false) {
                        continue;
                    }

                    foreach ($str_parser->getDefines() as $str_define) {
                        if ($str_define->getId() == $cur_define->getId() && dcUtils::versionsCompare($str_define->get('version'), $cur_define->get('version'), '>')) {
                            $str_define->set('repository', true);
                            $str_define->set('root', $cur_define->get('root'));
                            $str_define->set('root_writable', $cur_define->get('root_writable'));
                            $str_define->set('current_version', $cur_define->get('version'));

                            // if no update from main repository, add third party update
                            if (!isset($upd_versions[$str_define->getId()])) {
                                $upd_defines[] = $str_define;
                                // if update from third party repo is more recent than main repo, replace this last one
                            } elseif (dcUtils::versionsCompare($str_define->get('version'), $upd_versions[$str_define->getID()][1], '>')) {
                                $upd_defines[$upd_versions[$str_define->getId()][0]] = $str_define;

                                // This update is new from third party repository
                                if (dcStoreReader::readCode() === dcStoreReader::READ_FROM_SOURCE) {
                                    $this->has_new_update = true;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignore exceptions
                }
            }
        }

        // sort results by id
        uasort($new_defines, fn ($a, $b) => strtolower($a->getId()) <=> strtolower($b->getId()));
        uasort($upd_defines, fn ($a, $b) => strtolower($a->getId()) <=> strtolower($b->getId()));

        $this->defines = [
            'new'    => $new_defines,
            'update' => $upd_defines,
        ];

        // old style data
        foreach ($this->defines['new'] as $define) {
            $this->data['new'][$define->getId()] = $define->dump();
        }
        foreach ($this->defines['update'] as $define) {
            // keep only higher vesion
            if (!isset($this->data['update'][$define->getId()]) || dcUtils::versionsCompare($define->get('version'), $this->data['update'][$define->getId()]['version'], '>')) {
                $this->data['update'][$define->getId()] = $define->dump();
            }
        }

        return true;
    }

    /**
     * Check if repositories have new updates.
     *
     * @return  bool    True on new updates
     */
    public function hasNewUdpates(): bool
    {
        return $this->has_new_update;
    }

    /**
     * Get a list of modules.
     *
     * @param    bool    $update    True to get update modules, false for new ones
     *
     * @return    array    List of update/new modules defines
     */
    public function getDefines(bool $update = false): array
    {
        return $this->defines[$update ? 'update' : 'new'];
    }

    /**
     * Get a list of modules.
     *
     * @deprecated since 2.26 Use self::getDefines()
     *
     * @param    bool    $update    True to get update modules, false for new ones
     *
     * @return    array    List of update/new modules
     */
    public function get(bool $update = false): array
    {
        dcDeprecated::set('dcStore::getDefines()', '2.26');

        return $this->data[$update ? 'update' : 'new'];
    }

    /**
     * Search a module.
     *
     * Search string is cleaned, split and compare to split:
     * - module id and clean id,
     * - module name, clean name,
     * - module desccription.
     *
     * Every time a part of query is find on module,
     * result accuracy grow. Result is sorted by accuracy.
     *
     * @param    string    $pattern    String to search
     *
     * @return    array    Match modules defines
     */
    public function searchDefines(string $pattern): array
    {
        # Split query into small clean words
        if (!($patterns = self::patternize($pattern))) {
            return [];
        }

        # For each modules
        $defines = [];
        foreach ($this->defines['new'] as $define) {
            # Loop through required module fields
            foreach (self::$weighting as $field => $weight) {
                # Skip fields which not exsist on module
                if ($define->get($field) == '') {
                    continue;
                }

                # Split field value into small clean word
                if (!($subjects = self::patternize($define->get($field)))) {
                    continue;
                }

                # Check contents
                if (!($nb = preg_match_all('/(' . implode('|', $patterns) . ')/', implode(' ', $subjects), $_))) {
                    continue;
                }

                # Increment score by matches count * field weight
                $define->set('score', (int) $define->get('score') + $nb * $weight);
            }
            // return only scored modules
            if ($define->get('score')) {
                $defines[] = $define;
            }
        }
        # Sort response by matches count
        usort($defines, fn ($a, $b) => (int) $b->get('score') <=> (int) $a->get('score'));

        return $defines;
    }

    /**
     * Search a module.
     *
     * @deprecated since 2.26 Use self::searchDefines()
     *
     * @param    string    $pattern    String to search
     *
     * @return    array    Match modules
     */
    public function search(string $pattern): array
    {
        dcDeprecated::set('dcStore::searchDefines()', '2.26');

        $result = [];
        foreach ($this->searchDefines($pattern) as $define) {
            $result[$define->getId()] = $define->dump();
        }

        return $result;
    }

    /**
     * Quick download and install module.
     *
     * @param    string    $url    Module package URL
     * @param    string    $dest    Path to install module
     *
     * @return    int      dcModules::PACKAGE_INSTALLED (1), dcModules::PACKAGE_UPDATED (2)
     */
    public function process(string $url, string $dest): int
    {
        $this->download($url, $dest);

        return $this->install($dest);
    }

    /**
     * Download a module.
     *
     * @param    string    $url    Module package URL
     * @param    string    $dest    Path to put module package
     */
    public function download(string $url, string $dest): void
    {
        // Check and add default protocol if necessary
        if (!preg_match('%^https?:\/\/%', $url)) {
            $url = 'http://' . $url;
        }
        // Download package
        $path = '';
        if ($client = HttpClient::initClient($url, $path)) {
            try {
                $client->setUserAgent($this->user_agent);
                $client->useGzip(false);
                $client->setPersistReferers(false);
                $client->setOutput($dest);
                $client->get($path);
                unset($client);
            } catch (Exception $e) {
                unset($client);

                throw new Exception(__('An error occurred while downloading the file.'));
            }
        } else {
            throw new Exception(__('An error occurred while downloading the file.'));
        }
    }

    /**
     * Install a previously downloaded module.
     *
     * @param    string    $path    Path to module package
     *
     * @return    int        1 = installed, 2 = update
     */
    public function install(string $path): int
    {
        return dcModules::installPackage($path, $this->modules);
    }

    /**
     * User Agent String.
     *
     * @param    string    $str        User agent string
     */
    public function agent(string $str)
    {
        $this->user_agent = $str;
    }

    /**
     * Split and clean pattern.
     *
     * @param    string    $str        String to sanitize
     *
     * @return    array|false    Array of cleaned pieces of string or false if none
     */
    private static function patternize(string $str)
    {
        $arr = [];

        foreach (explode(' ', $str) as $_) {
            $_ = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $_));
            if (strlen($_) >= 2) {
                $arr[] = $_;
            }
        }

        return empty($arr) ? false : $arr;
    }
}
