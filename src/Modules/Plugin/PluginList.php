<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules\Plugin;

// Dotclear\Modules\Plugin\PluginList
use Dotclear\App;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Exception\AdminException;
use Dotclear\Modules\ModuleConfig;
use Dotclear\Modules\ModuleDefine;
use Dotclear\Modules\Modules;
use Dotclear\Modules\Repository\Repository;
use Exception;

/**
 * Modules list manager.
 *
 * @ingroup  Module
 */
class PluginList
{
    /**
     * @var string $list_id
     *             Current list id
     */
    protected $list_id = 'unknown';

    /**
     * @var array<string,ModuleDefine> $data
     *                                 Current modules list
     */
    protected $data = [];

    /**
     * @var false|string $path
     *                   Modules root directory
     */
    protected $path = false;

    /**
     * @var bool $path_writable
     *           Indicate if modules root directory is writable
     */
    protected $path_writable = false;

    /**
     * @var false|string $path_pattern
     *                   Directory pattern to work on
     */
    protected $path_pattern = false;

    /**
     * @var string $page_url
     *             Page URL
     */
    protected $page_url = '';

    /**
     * @var string $page_qs
     *             Page query string
     */
    protected $page_qs = '&';

    /**
     * @var string $page_tab
     *             Page tab
     */
    protected $page_tab = '';

    /**
     * @var string $page_redir
     *             Page redirection
     */
    protected $page_redir = '';

    /**
     * @var string $nav_indexes
     *             Index list
     */
    public static $nav_indexes = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /**
     * @var array<int,string> $nav_list
     *                        Index list with special index
     */
    protected $nav_list = [];

    /**
     * @var string $nav_special
     *             Text for other special index
     */
    protected $nav_special = 'other';

    /**
     * @var string $sort_field
     *             Field used to sort modules
     */
    protected $sort_field = 'sname';

    /**
     * @var bool $sort_asc
     *           Sort order asc
     */
    protected $sort_asc = true;

    /**
     * @var false|ModuleDefine $config_module
     *                         Module definition
     */
    protected $config_module = false;

    /**
     * @var false|ModuleConfig $config_class
     *                         Module class to configure
     */
    protected $config_class = false;

    /**
     * @var string $config_content
     *             Module configuration page content
     */
    protected $config_content = '';

    /**
     * @var Repository $store
     *                 Store instance
     */
    public $store;

    /**
     * @var bool $store_cache
     *           Use store result in cache
     */
    protected $store_cache = true;

    public function __construct(private Modules $modules)
    {
        if (!App::core()->processed('Admin')) {
            throw new Exception(__('Modules list only available on admin process.'));
        }

        $this->setPath();
        $this->setURL($this->getModulesURL());
        $this->setIndex(__('other'));
        $this->store = new Repository($this->modules(), $this->getStoreURL(), !$this->useStoreCache());
    }

    /**
     * Get Modules.
     *
     * @return Modules The modules instance
     */
    public function modules(): Modules
    {
        return $this->modules;
    }

    /**
     * Get manager Page URL.
     *
     * @param array<string,int|string> $param Additionnal URL params
     *
     * @return string The manager admin page URL
     */
    public function getModulesURL(array $param = []): string
    {
        return App::core()->adminurl()->get('admin.' . $this->modules()->getType(true), $param);
    }

    /**
     * Get a module Page URL.
     *
     * @param string                   $id    The module id
     * @param array<string,int|string> $param Additionnal URL params
     *
     * @return string The module admin page URL
     */
    public function getModuleURL(string $id, array $param = []): string
    {
        return App::core()->adminurl()->get('admin.' . $this->modules()->getType(true) . '.' . $id, $param);
    }

    /**
     * Sort modules list by specific field.
     *
     * @param array  $modules Array of modules
     * @param string $field   Field to sort from
     * @param bool   $asc     Sort asc if true, else decs
     *
     * @return array Array of sorted modules
     */
    public function sortModules(array $modules, string $field, bool $asc = true): array
    {
        $origin = $sorter = $final = [];

        foreach ($modules as $id => $module) {
            $properties = $module->properties();
            $origin[]   = $module;
            $sorter[]   = $properties[$field] ?? $field;
        }

        array_multisort($sorter, $asc ? SORT_ASC : SORT_DESC, $origin);

        foreach ($origin as $module) {
            $final[$module->id()] = $module;
        }

        return $final;
    }

    /**
     * Get Store.
     *
     * @return Repository The store instance
     */
    public function store(): Repository
    {
        return $this->store;
    }

    /**
     * Get manager store url.
     *
     * @return string The store URL
     */
    public function getStoreURL(): string
    {
        return (string) App::core()->blog()->settings()->get('system')->get('store_' . $this->modules()->getType(true) . '_url');
    }

    /**
     * Check if manager store use cache.
     *
     * @return bool True to use cache
     */
    public function useStoreCache(): bool
    {
        return empty($_GET['nocache']);
    }

    // / @name Modules list methods
    // @{
    /**
     * Begin a new list.
     *
     * @param string $id The new list id
     *
     * @return static self instance
     */
    public function setList(string $id): static
    {
        $this->page_tab  = '';
        $this->data      = [];
        $this->list_id   = $id;

        return $this;
    }

    /**
     * Get list ID.
     *
     * @return string The list ID
     */
    public function getList(): string
    {
        return $this->list_id;
    }
    // @}

    // / @name Modules root directory methods
    // @{
    /**
     * Set path info.
     */
    protected function setPath(): void
    {
        $paths = $this->modules()->getPaths();
        $path  = array_pop($paths);
        unset($paths);

        $this->path = $path;
        if (is_dir($path) && is_writeable($path)) {
            $this->path_writable = true;
            $this->path_pattern  = preg_quote($path, '!');
        }
    }

    /**
     * Get modules root directory.
     *
     * @return false|string Directory to work on
     */
    public function getPath(): string|false
    {
        return $this->path;
    }

    /**
     * Check if modules root directory is writable.
     *
     * @return bool True if directory is writable
     */
    public function isWritablePath(): bool
    {
        return $this->path_writable;
    }

    /**
     * Check if root directory of a module is deletable.
     *
     * @param string $root Module root directory
     *
     * @return bool True if directory is delatable
     */
    public function isDeletablePath(string $root): bool
    {
        return $this->path_writable
            && (preg_match('!^' . $this->path_pattern . '!', $root) || !App::core()->production())
            && App::core()->user()->isSuperAdmin();
    }
    // @}

    // / @name Page methods
    // @{
    /**
     * Set page base URL.
     *
     * @param string $url Page base URL
     *
     * @return static self instance
     */
    public function setURL(string $url): static
    {
        $this->page_qs  = str_contains($url, '?') ? '&' : '?';
        $this->page_url = $url;

        return $this;
    }

    /**
     * Get page URL.
     *
     * @param array|string $queries  Additionnal query string
     * @param bool         $with_tab Add current tab to URL end
     *
     * @return string Clean page URL
     */
    public function getURL(string|array $queries = '', bool $with_tab = true): string
    {
        return $this->page_url .
            (!empty($queries) ? $this->page_qs : '') .
            (is_array($queries) ? http_build_query($queries) : $queries) .
            ($with_tab && !empty($this->page_tab) ? '#' . $this->page_tab : '');
    }

    /**
     * Set page tab.
     *
     * @param string $tab Page tab
     *
     * @return static self instance
     */
    public function setTab(string $tab): static
    {
        $this->page_tab = $tab;

        return $this;
    }

    /**
     * Get page tab.
     *
     * @return string Page tab
     */
    public function getTab(): string
    {
        return $this->page_tab;
    }

    /**
     * Set page redirection.
     *
     * @param string $default Default redirection
     *
     * @return static self instance
     */
    public function setRedir(string $default = ''): static
    {
        $this->page_redir = empty($_REQUEST['redir']) ? $default : $_REQUEST['redir'];

        return $this;
    }

    /**
     * Get page redirection.
     *
     * @return string Page redirection
     */
    public function getRedir(): string
    {
        return empty($this->page_redir) ? $this->getURL() : $this->page_redir;
    }
    // @}

    // / @name Search methods
    // @{
    /**
     * Get search query.
     *
     * @return null|string Search query
     */
    public function getSearch(): ?string
    {
        $query = !empty($_REQUEST['m_search']) ? $_REQUEST['m_search'] : null;

        return strlen((string) $query) >= 2 ? $query : null;
    }

    /**
     * Display searh form.
     *
     * @return static self instance
     */
    public function displaySearch(): static
    {
        $query = $this->getSearch();

        if (empty($this->data) && null === $query) {
            return $this;
        }

        echo '<div class="modules-search">' .
        '<form action="' . $this->getURL() . '" method="get">' .
        '<p><label for="m_search" class="classic">' . __('Search in repository:') . '&nbsp;</label><br />' .
        Form::field('m_search', 30, 255, Html::escapeHTML($query)) .
        Form::hidden(['handler'], App::core()->adminurl()->called()) .
        '<input type="submit" value="' . __('OK') . '" /> ';

        if ($query) {
            echo ' <a href="' . $this->getURL() . '" class="button">' . __('Reset search') . '</a>';
        }

        echo '</p>' .
        '<p class="form-note">' .
        __('Search is allowed on multiple terms longer than 2 chars, terms must be separated by space.') .
            '</p>' .
            '</form>';

        if ($query) {
            echo '<p class="message">' . sprintf(
                __('Found %d result for search "%s":', 'Found %d results for search "%s":', count($this->data)),
                count($this->data),
                Html::escapeHTML($query)
            ) .
                '</p>';
        }
        echo '</div>';

        return $this;
    }
    // @}

    // / @name Navigation menu methods
    // @{
    /**
     * Set navigation special index.
     *
     * @param string $str Nav index
     *
     * @return static self instance
     */
    public function setIndex(string $str): static
    {
        $this->nav_special = (string) $str;
        $this->nav_list    = array_merge(str_split(self::$nav_indexes), [$this->nav_special]);

        return $this;
    }

    /**
     * Get index from query.
     *
     * @return string Query index or default one
     */
    public function getIndex(): string
    {
        return isset($_REQUEST['m_nav']) && in_array($_REQUEST['m_nav'], $this->nav_list) ? $_REQUEST['m_nav'] : $this->nav_list[0];
    }

    /**
     * Display navigation by index menu.
     *
     * @return static self instance
     */
    public function displayIndex(): static
    {
        if (empty($this->data) || $this->getSearch() !== null) {
            return $this;
        }

        // Fetch modules required field
        $indexes = [];
        foreach ($this->data as $id => $module) {
            $properties = $module->properties();
            if (!isset($properties[$this->sort_field])) {
                continue;
            }
            $char = substr($properties[$this->sort_field], 0, 1);
            if (!in_array($char, $this->nav_list)) {
                $char = $this->nav_special;
            }
            if (!isset($properties[$char])) {
                $indexes[$char] = 0;
            }
            ++$indexes[$char];
        }

        $buttons = [];
        foreach ($this->nav_list as $char) {
            // Selected letter
            if ($this->getIndex() == $char) {
                $buttons[] = '<li class="active" title="' . __('current selection') . '"><strong> ' . $char . ' </strong></li>';
            }
            // Letter having modules
            elseif (!empty($indexes[$char])) {
                $title     = sprintf(__('%d result', '%d results', $indexes[$char]), $indexes[$char]);
                $buttons[] = '<li class="btn" title="' . $title . '"><a href="' . $this->getURL('m_nav=' . $char) . '" title="' . $title . '"> ' . $char . ' </a></li>';
            }
            // Letter without modules
            else {
                $buttons[] = '<li class="btn no-link" title="' . __('no results') . '"> ' . $char . ' </li>';
            }
        }
        // Parse navigation menu
        echo '<div class="pager">' . __('Browse index:') . ' <ul class="index">' . implode('', $buttons) . '</ul></div>';

        return $this;
    }
    // @}

    // / @name Sort methods
    // @{
    /**
     * Set default sort field.
     *
     * @param string $field Sort field
     * @param bool   $asc   Sort asc
     *
     * @return static self instance
     */
    public function setSort(string $field, bool $asc = true): static
    {
        $this->sort_field = $field;
        $this->sort_asc   = (bool) $asc;

        return $this;
    }

    /**
     * Get sort field from query.
     *
     * @return string Query sort field or default one
     */
    public function getSort(): string
    {
        return !empty($_REQUEST['m_sort']) ? $_REQUEST['m_sort'] : $this->sort_field;
    }

    /**
     * Display sort field form.
     *
     * @note    This method is not implemented yet
     *
     * @return static self instance
     */
    public function displaySort(): static
    {
        return $this;
    }
    // @}

    // / @name Data methods
    // @{
    /**
     * Set modules.
     *
     * @param array<string,ModuleDefine> $modules The modules
     *
     * @return static self instance
     */
    public function setData(array $modules): static
    {
        $this->data = [];
        if (!empty($modules) && is_array($modules)) {
            foreach ($modules as $id => $module) {
                if (is_a($module, 'Dotclear\\Modules\\ModuleDefine') && $module->type() == $this->modules()->getType()) {
                    $this->data[$id] = $module;
                }
            }
        }

        return $this;
    }

    /**
     * Get modules currently set.
     *
     * @return array Array of modules
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Display list of modules.
     *
     * Fields can be checkbox, icon, and fields from TraitDefine, TraitDefinePlugin for available fields
     *
     * @param array $cols      List of colones (module field) to display
     * @param array $actions   List of predefined actions to show on form
     * @param bool  $nav_limit Limit list to previously selected index
     *
     * @return static self instance
     */
    public function displayData(array $cols = ['name', 'version', 'description'], array $actions = [], bool $nav_limit = false): static
    {
        echo '<form action="' . $this->getURL() . '" method="post" class="modules-form-actions">' .
        '<div class="table-outer">' .
        '<table id="' . Html::escapeHTML($this->list_id) . '" class="modules' . (in_array('expander', $cols) ? ' expandable' : '') . '">' .
        '<caption class="hidden">' . Html::escapeHTML(__('Modules list')) . '</caption><tr>';

        if (in_array('name', $cols)) {
            $colspan = 1;
            if (in_array('checkbox', $cols)) {
                ++$colspan;
            }
            if (in_array('icon', $cols)) {
                ++$colspan;
            }
            echo '<th class="first nowrap"' . (1 < $colspan ? ' colspan="' . $colspan . '"' : '') . '>' . __('Name') . '</th>';
        }

        if (in_array('score', $cols) && $this->getSearch() !== null && !App::core()->production()) {
            echo '<th class="nowrap">' . __('Score') . '</th>';
        }

        if (in_array('author', $cols) && !in_array('expander', $cols)) {
            echo '<th class="nowrap module-author" scope="col">' . __('Author') . '</th>';
        }

        if (in_array('version', $cols)) {
            echo '<th class="nowrap count" scope="col">' . __('Version') . '</th>';
        }

        if (in_array('current_version', $cols)) {
            echo '<th class="nowrap count" scope="col">' . __('Current version') . '</th>';
        }

        if (in_array('description', $cols)) {
            echo '<th class="nowrap module-desc" scope="col">' . __('Details') . '</th>';
        }

        if (in_array('repository', $cols) && App::core()->config()->get('store_allow_repo')) {
            echo '<th class="nowrap count" scope="col">' . __('Repository') . '</th>';
        }

        if (in_array('distrib', $cols)) {
            echo '<th' . (in_array('description', $cols) ? '' : ' class="maximal"') . '></th>';
        }

        if (!empty($actions) && App::core()->user()->isSuperAdmin()) {
            echo '<th class="minimal nowrap">' . __('Action') . '</th>';
        }

        echo '</tr>';

        $sort_field = $this->getSort();

        // Sort modules by $sort_field (default sname)
        $modules = $this->getSearch() === null ? $this->sortModules($this->data, $sort_field, $this->sort_asc) : $this->data;

        $count = 0;
        foreach ($modules as $id => $module) {
            // Show only requested modules
            if ($nav_limit && $this->getSearch() === null) {
                $properties = $module->properties();
                $char       = substr($properties[$sort_field], 0, 1);
                if (!in_array($char, $this->nav_list)) {
                    $char = $this->nav_special;
                }
                if ($this->getIndex() != $char) {
                    continue;
                }
            }

            echo '<tr class="line" id="' . Html::escapeHTML($this->list_id) . '_m_' . Html::escapeHTML($id) . '"' .
                (in_array('description', $cols) ? ' title="' . Html::escapeHTML($module->description()) . '" ' : '') .
                '>';

            $tds = 0;

            if (in_array('checkbox', $cols)) {
                ++$tds;
                echo '<td class="module-icon nowrap">' .
                Form::checkbox(['modules[' . $count . ']', Html::escapeHTML($this->list_id) . '_modules_' . Html::escapeHTML($id)], Html::escapeHTML($id)) .
                    '</td>';
            }

            if (in_array('icon', $cols)) {
                ++$tds;
                if (file_exists($module->root() . '/icon.svg')) {
                    $icon = '?df=' . $module->type() . '/' . $id . '/icon.svg';
                } elseif (file_exists($module->root() . '/icon.png')) {
                    $icon = '?df=' . $module->type() . '/' . $id . '/icon.png';
                } else {
                    $icon = 'images/module.png';
                }
                if (file_exists($module->root() . '/icon-dark.svg')) {
                    $icon = [$icon, '?df=' . $module->type() . '/' . $id . '/icon-dark.svg'];
                } elseif (file_exists($module->root() . '/icon-dark.png')) {
                    $icon = [$icon, '?df=' . $module->type() . '/' . $id . '/icon-dark.png'];
                }

                echo '<td class="module-icon nowrap">' .
                App::core()->summary()->getIconTheme($icon, false, Html::escapeHTML($id), Html::escapeHTML($id)) .
                '</td>';
            }

            ++$tds;
            echo '<th class="module-name nowrap" scope="row">';
            if (in_array('checkbox', $cols)) {
                if (in_array('expander', $cols)) {
                    echo Html::escapeHTML($module->name()) . ($module->name() != $id ? sprintf(__(' (%s)'), $id) : '');
                } else {
                    echo '<label for="' . Html::escapeHTML($this->list_id) . '_modules_' . Html::escapeHTML($id) . '">' .
                    Html::escapeHTML($module->name()) . ($module->name() != $id ? sprintf(__(' (%s)'), $id) : '') .
                        '</label>';
                }
            } else {
                echo Html::escapeHTML($module->name()) . ($module->name() != $id ? sprintf(__(' (%s)'), $id) : '') .
                Form::hidden(['modules[' . $count . ']'], Html::escapeHTML($id));
            }
            echo App::core()->nonce()->form() .
                '</td>';

            // Display score only for debug purpose
            if (in_array('score', $cols) && $this->getSearch() !== null && !App::core()->production()) {
                ++$tds;
                echo '<td class="module-version nowrap count"><span class="debug">' . $module->score() . '</span></td>';
            }

            if (in_array('author', $cols) && !in_array('expander', $cols)) {
                ++$tds;
                echo '<td class="module-author nowrap">' . Html::escapeHTML($module->author()) . '</td>';
            }

            if (in_array('version', $cols)) {
                ++$tds;
                echo '<td class="module-version nowrap count">' . Html::escapeHTML($module->version()) . '</td>';
            }

            if (in_array('current_version', $cols)) {
                ++$tds;
                echo '<td class="module-current-version nowrap count">' . Html::escapeHTML($module->currentVersion()) . '</td>';
            }

            if (in_array('description', $cols)) {
                ++$tds;
                echo '<td class="module-desc maximal">' . Html::escapeHTML($module->description());
                if (!empty($module->depChildren()) && $module->enabled()) {
                    echo '<br/><span class="info">' .
                    sprintf(
                        __('This module cannot be disabled nor deleted, since the following modules are also enabled : %s'),
                        join(',', $module->depChildren())
                    ) .
                        '</span>';
                }
                if (!empty($module->depMissing()) && !$module->enabled()) {
                    echo '<br/><span class="info">' .
                    __('This module cannot be enabled, because of the following reasons :') .
                        '<ul>';
                    foreach ($module->depMissing() as $m => $reason) {
                        echo '<li>' . $reason . '</li>';
                    }
                    echo '</ul>' .
                        '</span>';
                }
                echo '</td>';
            }

            if (in_array('repository', $cols) && App::core()->config()->get('store_allow_repo')) {
                ++$tds;
                echo '<td class="module-repository nowrap count">' . (!empty($module->repository()) ? __('Third-party repository') : __('Official repository')) . '</td>';
            }

            if (in_array('distrib', $cols)) {
                ++$tds;
                echo '<td class="module-distrib">' . ($this->modules()->isDistributedModule($id) ?
                    '<img src="?df=images/dotclear_pw.png" alt="' .
                    __('Plugin from official distribution') . '" title="' .
                    __('Plugin from official distribution') . '" />'
                    : '') . '</td>';
            }

            if (!empty($actions) && App::core()->user()->isSuperAdmin()) {
                $buttons = $this->getActions($id, $module, $actions);

                ++$tds;
                echo '<td class="module-actions nowrap">' .

                '<div>' . implode(' ', $buttons) . '</div>' .

                    '</td>';
            }

            echo '</tr>';

            // Other informations
            if (in_array('expander', $cols)) {
                echo '<tr class="module-more"><td colspan="' . $tds . '" class="expand">';

                if (!empty($module->author()) || !empty($module->details()) || !empty($module->support())) {
                    echo '<div><ul class="mod-more">';

                    if (!empty($module->author())) {
                        echo '<li class="module-author">' . __('Author:') . ' ' . Html::escapeHTML($module->author()) . '</li>';
                    }

                    $more = [];
                    if (!empty($module->details())) {
                        $more[] = '<a class="module-details" href="' . $module->details() . '">' . __('Details') . '</a>';
                    }

                    if (!empty($module->support())) {
                        $more[] = '<a class="module-support" href="' . $module->support() . '">' . __('Support') . '</a>';
                    }

                    if (!empty($more)) {
                        echo '<li>' . implode(' - ', $more) . '</li>';
                    }

                    echo '</ul></div>';
                }

                $config = is_subclass_of(
                    'Dotclear\\' . $module->type() . '\\' . $id . '\\Admin\\Config',
                    'Dotclear\\Modules\\ModuleConfig'
                );
                $index = is_subclass_of(
                    'Dotclear\\' . $module->type() . '\\' . $id . '\\Admin\\Handler',
                    'Dotclear\\Process\\Admin\\Page\\AbstractPage'
                );

                if ($config || $index || !empty($module->section()) || !empty($module->tags()) || !empty($module->settings())
                    || !empty($module->repository()) && App::core()->config()->get('store_allow_repo') && !App::core()->production()
                ) {
                    echo '<div><ul class="mod-more">';

                    $settings = $this->getSettingsUrls($id);
                    if (!empty($settings) && $module->enabled()) {
                        echo '<li>' . implode(' - ', $settings) . '</li>';
                    }

                    if (!empty($module->repository()) && App::core()->config()->get('store_allow_repo') && !App::core()->production()) {
                        echo '<li class="modules-repository"><a href="' . $module->repository() . '">' . __('Third-party repository') . '</a></li>';
                    }

                    if (!empty($module->section())) {
                        echo '<li class="module-section">' . __('Section:') . ' ' . Html::escapeHTML($module->section()) . '</li>';
                    }

                    if (!empty($module->tags())) {
                        echo '<li class="module-tags">' . __('Tags:') . ' ' . Html::escapeHTML(implode(',', $module->tags())) . '</li>';
                    }

                    echo '</ul></div>';
                }

                echo '</td></tr>';
            }

            ++$count;
        }
        echo '</table></div>';

        if (!$count && $this->getSearch() === null) {
            echo '<p class="message">' . __('No modules matched your search.') . '</p>';
        } elseif ((in_array('checkbox', $cols) || 1 < $count) && !empty($actions) && App::core()->user()->isSuperAdmin()) {
            $buttons = $this->getGlobalActions($actions, in_array('checkbox', $cols));

            if (!empty($buttons)) {
                if (in_array('checkbox', $cols)) {
                    echo '<p class="checkboxes-helpers"></p>';
                }
                echo '<div>' . implode(' ', $buttons) . '</div>';
            }
        }
        echo '</form>';

        return $this;
    }

    /**
     * Get settings URLs if any.
     *
     * @param string $id    Module ID
     * @param bool   $check Check permission
     * @param bool   $self  Include self URL
     *
     * @return array Array of settings URLs
     */
    public function getSettingsUrls(string $id, bool $check = false, bool $self = true): array
    {
        // Check if module exists
        $module = $this->modules()->getModule($id);
        if (!$module) {
            return [];
        }
        // Reset
        $st     = [];
        $config = is_subclass_of(
            'Dotclear\\' . $module->type() . '\\' . $id . '\\Admin\\Config',
            'Dotclear\\Modules\\ModuleConfig'
        );
        $index = is_subclass_of(
            'Dotclear\\' . $module->type() . '\\' . $id . '\\Admin\\Handler',
            'Dotclear\\Process\\Admin\\Page\\AbstractPage'
        );

        $settings = $module->settings();
        if ($self && isset($settings['self']) && empty($settings['self'])) {
            $self = false;
        }
        if ($config || $index || !empty($settings)) {
            if ($config && (!$check || App::core()->user()->isSuperAdmin() || App::core()->user()->check($module->permissions(), App::core()->blog()->id))) {
                $params = ['module' => $id, 'conf' => '1'];
                if (!$module->standaloneConfig() && !$self) {
                    $params['redir'] = $this->getModuleURL($id);
                }
                $st['config'] = '<a class="module-config" href="' .
                $this->getModulesURL($params) .
                '">' . __('Configure module') . '</a>';
            }

            foreach ($settings as $sk => $sv) {
                switch ($sk) {
                    case 'blog':
                        if (!$check || App::core()->user()->isSuperAdmin() || App::core()->user()->check('admin', App::core()->blog()->id)) {
                            $st['blog'] = '<a class="module-config" href="' .
                            App::core()->adminurl()->get('admin.blog.pref') . $sv .
                            '">' . __('Module settings (in blog parameters)') . '</a>';
                        }

                        break;

                    case 'pref':
                        if (!$check || App::core()->user()->isSuperAdmin() || App::core()->user()->check('usage,contentadmin', App::core()->blog()->id)) {
                            $st['pref'] = '<a class="module-config" href="' .
                            App::core()->adminurl()->get('admin.user.pref') . $sv .
                            '">' . __('Module settings (in user preferences)') . '</a>';
                        }

                        break;

                    case 'self':
                        if ($self) {
                            if (!$check || App::core()->user()->isSuperAdmin() || App::core()->user()->check($module->permissions(), App::core()->blog()->id)) {
                                $st['self'] = '<a class="module-config" href="' .
                                $this->getModuleURL($id) . $sv .
                                '">' . __('Module settings') . '</a>';
                            }
                            // No need to use default module handler
                            $index = false;
                        }

                        break;

                    case 'other':
                        if (!$check || App::core()->user()->isSuperAdmin() || App::core()->user()->check($module->permissions(), App::core()->blog()->id)) {
                            $st['other'] = '<a class="module-config" href="' .
                            $sv .
                            '">' . __('Module settings') . '</a>';
                        }

                        break;
                }
            }
            if ($index && $self && (!$check || App::core()->user()->isSuperAdmin() || App::core()->user()->check($module->permissions(), App::core()->blog()->id))) {
                $st['index'] = '<a class="module-config" href="' .
                $this->getModuleURL($id) .
                '">' . __('Dedicated module page') . '</a>';
            }
        }

        return $st;
    }

    /**
     * Get action buttons to add to modules list.
     *
     * @param string       $id      The module ID
     * @param ModuleDefine $module  The module info
     * @param array        $actions Actions keys
     *
     * @return array Array of actions buttons
     */
    protected function getActions(string $id, ModuleDefine $module, array $actions): array
    {
        $submits = [];

        // Use loop to keep requested order
        foreach ($actions as $action) {
            switch ($action) {
                // Deactivate
                case 'activate':
                    if (App::core()->user()->isSuperAdmin() && $module->writable() && empty($module->depMissing())) {
                        $submits[] = '<input type="submit" name="activate[' . Html::escapeHTML($id) . ']" value="' . __('Activate') . '" />';
                    }

                    break;
                // Activate
                case 'deactivate':
                    if (App::core()->user()->isSuperAdmin() && $module->writable() && empty($module->depChildren())) {
                        $submits[] = '<input type="submit" name="deactivate[' . Html::escapeHTML($id) . ']" value="' . __('Deactivate') . '" class="reset" />';
                    }

                    break;
                // Delete
                case 'delete':
                    if (App::core()->user()->isSuperAdmin() && $this->isDeletablePath($module->root()) && empty($module->depChildren())) {
                        $dev       = !preg_match('!^' . $this->path_pattern . '!', $module->root()) && !App::core()->production() ? ' debug' : '';
                        $submits[] = '<input type="submit" class="delete ' . $dev . '" name="delete[' . Html::escapeHTML($id) . ']" value="' . __('Delete') . '" />';
                    }

                    break;
                // Clone
                case 'clone':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" class="button clone" name="clone[' . Html::escapeHTML($id) . ']" value="' . __('Clone') . '" />';
                    }

                    break;
                // Install (from store)
                case 'install':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="install[' . Html::escapeHTML($id) . ']" value="' . __('Install') . '" />';
                    }

                    break;
                // Update (from store)
                case 'update':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update[' . Html::escapeHTML($id) . ']" value="' . __('Update') . '" />';
                    }

                    break;
                // Behavior
                case 'behavior':
                    // --BEHAVIOR-- adminModulesListGetActions
                    $tmp = App::core()->behavior()->call('adminModulesListGetActions', $this, $id, $module);

                    if (!empty($tmp)) {
                        $submits[] = $tmp;
                    }

                    break;
            }
        }

        return $submits;
    }

    /**
     * Get global action buttons to add to modules list.
     *
     * @param array $actions        Actions keys
     * @param bool  $with_selection Limit action to selected modules
     *
     * @return array Array of actions buttons
     */
    protected function getGlobalActions(array $actions, bool $with_selection = false): array
    {
        $submits = [];

        // Use loop to keep requested order
        foreach ($actions as $action) {
            switch ($action) {
                // Deactivate
                case 'activate':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="activate" value="' . (
                            $with_selection ?
                            __('Activate selected modules') :
                            __('Activate all modules from this list')
                        ) . '" />';
                    }

                    break;
                // Activate
                case 'deactivate':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="deactivate" value="' . (
                            $with_selection ?
                            __('Deactivate selected modules') :
                            __('Deactivate all modules from this list')
                        ) . '" />';
                    }

                    break;
                // Update (from store)
                case 'update':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update" value="' . (
                            $with_selection ?
                            __('Update selected modules') :
                            __('Update all modules from this list')
                        ) . '" />';
                    }

                    break;
                // Behavior
                case 'behavior':
                    // --BEHAVIOR-- adminModulesListGetGlobalActions
                    $tmp = App::core()->behavior()->call('adminModulesListGetGlobalActions', $this, $with_selection);

                    if (!empty($tmp)) {
                        $submits[] = $tmp;
                    }

                    break;
            }
        }

        return $submits;
    }

    /**
     * Execute POST action.
     *
     * @uses    Notices::addSuccessNotice    Set a notice on success through Notices::addSuccessNotice
     *
     * throws  AdminException              Module not find or command failed
     */
    public function doActions(): void
    {
        if (empty($_POST) || !empty($_REQUEST['conf']) || !$this->isWritablePath()) {
            return;
        }

        $modules = !empty($_POST['modules']) && is_array($_POST['modules']) ? array_values($_POST['modules']) : [];

        // Delete
        if (App::core()->user()->isSuperAdmin() && !empty($_POST['delete'])) {
            if (is_array($_POST['delete'])) {
                $modules = array_keys($_POST['delete']);
            }

            $list = $this->modules()->getDisabledModules();

            $failed = false;
            $count  = 0;
            foreach ($modules as $id) {
                if (!isset($list[$id])) {
                    if (!$this->modules()->hasModule($id)) {
                        throw new AdminException(__('No such plugin.'));
                    }

                    $module = $this->modules()->getModule($id);

                    if (!$this->isDeletablePath($module->root())) {
                        $failed = true;

                        continue;
                    }

                    // --BEHAVIOR-- moduleBeforeDelete
                    App::core()->behavior()->call('pluginBeforeDelete', $module);

                    $this->modules()->deleteModule($id);

                    // --BEHAVIOR-- moduleAfterDelete
                    App::core()->behavior()->call('pluginAfterDelete', $module);
                } else {
                    $this->modules()->deleteModule($id, true);
                }

                ++$count;
            }

            if (!$count && $failed) {
                throw new AdminException(__("You don't have permissions to delete this plugin."));
            }
            if ($failed) {
                App::core()->notice()->addWarningNotice(__('Some plugins have not been delete.'));
            } else {
                App::core()->notice()->addSuccessNotice(
                    __('Plugin has been successfully deleted.', 'Plugins have been successuflly deleted.', $count)
                );
            }
            Http::redirect($this->getURL());

        // Install //! waiting for store modules to be from ModuleDefine
        } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['install'])) {
            if (is_array($_POST['install'])) {
                $modules = array_keys($_POST['install']);
            }

            $list = $this->store()->get();

            if (empty($list)) {
                throw new AdminException(__('No such plugin.'));
            }

            $count = 0;
            foreach ($list as $id => $module) {
                if (!in_array($id, $modules)) {
                    continue;
                }

                $dest = $this->getPath() . '/' . basename($module->file());

                // --BEHAVIOR-- moduleBeforeAdd
                App::core()->behavior()->call('pluginBeforeAdd', $module);

                $this->store()->process($module->file(), $dest);

                // --BEHAVIOR-- moduleAfterAdd
                App::core()->behavior()->call('pluginAfterAdd', $module);

                ++$count;
            }

            App::core()->notice()->addSuccessNotice(
                __('Plugin has been successfully installed.', 'Plugins have been successfully installed.', $count)
            );
            Http::redirect($this->getURL());

        // Activate
        } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['activate'])) {
            if (is_array($_POST['activate'])) {
                $modules = array_keys($_POST['activate']);
            }

            $list = $this->modules()->getDisabledModules();
            if (empty($list)) {
                throw new AdminException(__('No such plugin.'));
            }

            $count = 0;
            foreach ($list as $id => $module) {
                if (!in_array($id, $modules)) {
                    continue;
                }

                // --BEHAVIOR-- moduleBeforeActivate
                App::core()->behavior()->call('pluginBeforeActivate', $id);

                $this->modules()->activateModule($id);

                // --BEHAVIOR-- moduleAfterActivate
                App::core()->behavior()->call('pluginAfterActivate', $id);

                ++$count;
            }

            App::core()->notice()->addSuccessNotice(
                __('Plugin has been successfully activated.', 'Plugins have been successuflly activated.', $count)
            );
            Http::redirect($this->getURL());

        // Deactivate
        } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['deactivate'])) {
            if (is_array($_POST['deactivate'])) {
                $modules = array_keys($_POST['deactivate']);
            }

            $list = $this->modules()->getModules();
            if (empty($list)) {
                throw new AdminException(__('No such plugin.'));
            }

            $failed = false;
            $count  = 0;
            foreach ($list as $id => $module) {
                if (!in_array($id, $modules)) {
                    continue;
                }

                if (!$module->writable()) {
                    $failed = true;

                    continue;
                }

                // --BEHAVIOR-- moduleBeforeDeactivate
                App::core()->behavior()->call('pluginBeforeDeactivate', $module);

                $this->modules()->deactivateModule($id);

                // --BEHAVIOR-- moduleAfterDeactivate
                App::core()->behavior()->call('pluginAfterDeactivate', $module);

                ++$count;
            }

            if ($failed) {
                App::core()->notice()->addWarningNotice(__('Some plugins have not been deactivated.'));
            } else {
                App::core()->notice()->addSuccessNotice(
                    __('Plugin has been successfully deactivated.', 'Plugins have been successuflly deactivated.', $count)
                );
            }
            Http::redirect($this->getURL());

        // Update //! waiting for store modules to be from ModuleDefine
        } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['update'])) {
            if (is_array($_POST['update'])) {
                $modules = array_keys($_POST['update']);
            }

            $list = $this->store()->get(true);
            if (empty($list)) {
                throw new AdminException(__('No such plugin.'));
            }

            $count = 0;
            foreach ($list as $id => $module) {
                if (!in_array($id, $modules)) {
                    continue;
                }

                if (App::core()->config()->get('module_allow_multi')) {
                    $dest = $module->root() . '/../' . basename($module->file());
                } else {
                    $dest = $this->getPath() . '/' . basename($module->file());
                    if ($module->root() != $dest) {
                        @file_put_contents($module->root() . '/_disabled', '');
                    }
                }

                // --BEHAVIOR-- moduleBeforeUpdate
                App::core()->behavior()->call('pluginBeforeUpdate', $module);

                $this->store()->process($module->file(), $dest);

                // --BEHAVIOR-- moduleAfterUpdate
                App::core()->behavior()->call('pluginAfterUpdate', $module);

                ++$count;
            }

            $tab = $count && count($list) == $count ? '#plugins' : '#update';

            App::core()->notice()->addSuccessNotice(
                __('Plugin has been successfully updated.', 'Plugins have been successfully updated.', $count)
            );
            Http::redirect($this->getURL() . $tab);
        }

        // Manual actions
        elseif (!empty($_POST['upload_pkg']) && !empty($_FILES['pkg_file'])
            || !empty($_POST['fetch_pkg']) && !empty($_POST['pkg_url'])) {
            if (empty($_POST['your_pwd']) || !App::core()->user()->checkPassword($_POST['your_pwd'])) {
                throw new AdminException(__('Password verification failed'));
            }

            if (!empty($_POST['upload_pkg'])) {
                Files::uploadStatus($_FILES['pkg_file']);

                $dest = $this->getPath() . '/' . $_FILES['pkg_file']['name'];
                if (!move_uploaded_file($_FILES['pkg_file']['tmp_name'], $dest)) {
                    throw new AdminException(__('Unable to move uploaded file.'));
                }
            } else {
                $url  = urldecode($_POST['pkg_url']);
                $dest = $this->getPath() . '/' . basename($url);
                $this->store()->download($url, $dest);
            }

            // --BEHAVIOR-- moduleBeforeAdd
            App::core()->behavior()->call('pluginBeforeAdd', null);

            $ret_code = $this->store()->install($dest);

            // --BEHAVIOR-- moduleAfterAdd
            App::core()->behavior()->call('pluginAfterAdd', null);

            App::core()->notice()->addSuccessNotice(
                2 == $ret_code ?
                __('The plugin has been successfully updated.') :
                __('The plugin has been successfully installed.')
            );
            Http::redirect($this->getURL() . '#plugins');

        // Actions from behaviors
        } else {
            // --BEHAVIOR-- adminModulesListDoActions
            App::core()->behavior()->call('adminModulesListDoActions', $this, $modules, 'plugin');
        }
    }

    /**
     * Display tab for manual installation.
     *
     * @return static self instance or null
     */
    public function displayManualForm(): static
    {
        if (!App::core()->user()->isSuperAdmin() || !$this->isWritablePath()) {
            return $this;
        }

        // 'Upload module' form
        echo '<form method="post" action="' . $this->getURL() . '" id="uploadpkg" enctype="multipart/form-data" class="fieldset">' .
        '<h4>' . __('Upload a zip file') . '</h4>' .
        '<p class="field"><label for="pkg_file" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Zip file path:') . '</label> ' .
        '<input type="file" name="pkg_file" id="pkg_file" required /></p>' .
        '<p class="field"><label for="your_pwd1" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
        Form::password(
            ['your_pwd', 'your_pwd1'],
            20,
            255,
            [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'current-password',
            ]
        ) . '</p>' .
        '<p><input type="submit" name="upload_pkg" value="' . __('Upload') . '" />' .
        App::core()->nonce()->form() . '</p>' .
            '</form>';

        // 'Fetch module' form
        echo '<form method="post" action="' . $this->getURL() . '" id="fetchpkg" class="fieldset">' .
        '<h4>' . __('Download a zip file') . '</h4>' .
        '<p class="field"><label for="pkg_url" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Zip file URL:') . '</label> ' .
        Form::field('pkg_url', 40, 255, [
            'extra_html' => 'required placeholder="' . __('URL') . '"',
        ]) .
        '</p>' .
        '<p class="field"><label for="your_pwd2" class="classic required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label> ' .
        Form::password(
            ['your_pwd', 'your_pwd2'],
            20,
            255,
            [
                'extra_html'   => 'required placeholder="' . __('Password') . '"',
                'autocomplete' => 'current-password',
            ]
        ) . '</p>' .
        '<p><input type="submit" name="fetch_pkg" value="' . __('Download') . '" />' .
        App::core()->nonce()->form() . '</p>' .
            '</form>';

        return $this;
    }
    // @}

    // / @name Module configuration methods
    // @{
    /**
     * Prepare module configuration.
     *
     * @param null|string $id Module to work on or it gather through REQUEST
     *
     * @return bool True if config set
     */
    public function loadModuleConfiguration(?string $id = null): bool
    {
        // Check request
        if (empty($_REQUEST['conf']) || empty($_REQUEST['module']) && !$id) {
            return false;
        }
        if (!empty($_REQUEST['module']) && empty($id)) {
            $id = $_REQUEST['module'];
        }

        // Check module and get its definition
        $module = $this->modules()->getModule($id);
        if (!$module) {
            App::core()->error()->add(__('Unknown module ID'));

            return false;
        }

        // Check config
        $class = 'Dotclear\\' . $module->type() . '\\' . $module->id() . '\\' . App::core()->processed() . '\\Config';
        if (!is_subclass_of($class, 'Dotclear\\Modules\\ModuleConfig')) {
            App::core()->error()->add(__('This module has no configuration file.'));

            return false;
        }

        $class = new $class($this->getURL(['module' => $module->id(), 'conf' => 1]));

        // Check permissions
        if (!App::core()->user()->check($class->getPermissions(), App::core()->blog()->id)) {
            App::core()->error()->add(__('Insufficient permissions'));

            return false;
        }

        $this->config_module  = $module;
        $this->config_class   = $class;
        $this->config_content = '';
        $this->setRedir($this->getURL() . '#modules');

        return true;
    }

    public function parseModuleConfiguration(): bool
    {
        if (!$this->config_class) {
            return false;
        }

        try {
            // Save changes
            if (!empty($_POST)) {
                $this->config_class->setConfiguration($_POST);
            }

            // Get form content
            ob_start();

            $this->config_class->getConfiguration();
            $this->config_content = ob_get_contents();

            ob_end_clean();
        } catch (Exception $e) {
            ob_end_clean();
            App::core()->error()->add($e->getMessage());
        }

        return !empty($this->config_content);
    }

    /**
     * Display module configuration form.
     *
     * @note Required previously gathered content
     *
     * @return string Module configuration form
     */
    public function displayModuleConfiguration(): string
    {
        if (!$this->config_class) {
            return '';
        }

        if ($this->config_module->standaloneConfig()) {
            return $this->config_content;
        }

        $links = $this->getSettingsUrls($this->config_module->id());
        unset($links['config']);

        return
        '<form id="module_config" action="' . $this->getURL('conf=1') . '" method="post" enctype="multipart/form-data">' .
        '<h3>' . sprintf(__('Configure "%s"'), Html::escapeHTML($this->config_module->name())) . '</h3>' .
        '<p><a class="back" href="' . $this->getRedir() . '">' . __('Back') . '</a></p>' .

        $this->config_content .

        '<p class="clear"><input type="submit" name="save" value="' . __('Save') . '" />' .
        Form::hidden('module', $this->config_module->id()) .
        Form::hidden('redir', $this->getRedir()) .
        App::core()->nonce()->form() . '</p>' .
            '</form>' .

        (empty($links) ? '' : sprintf('<hr class="clear"/><p class="right modules">%s</p>', implode(' - ', $links)));
    }
    // @}
}
