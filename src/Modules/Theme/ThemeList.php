<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Modules\Theme;

// Dotclear\Modules\Theme\ThemeList
use Dotclear\App;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Modules\ModuleDefine;
use Dotclear\Modules\Modules;
use Dotclear\Modules\Plugin\PluginList;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * Theme modules admin methods.
 *
 * @ingroup  Module Admin Theme
 */
class ThemeList extends PluginList
{
    public function displayData(array $cols = ['name', 'version', 'description'], array $actions = [], bool $nav_limit = false): static
    {
        echo '<form action="' . $this->getURL() . '" method="post" class="modules-form-actions">' .
        '<div id="' . Html::escapeHTML($this->list_id) . '" class="modules' . (in_array('expander', $cols) ? ' expandable' : '') . ' one-box">';

        $sort_field = $this->getSort();

        // Sort modules by id
        $modules = null === $this->getSearch() ? $this->sortModules($this->data, $sort_field, $this->sort_asc) : $this->data;

        $res   = '';
        $count = 0;
        foreach ($modules as $id => $module) {
            // Show only requested modules
            if ($nav_limit && null === $this->getSearch()) {
                $char = substr((string) $module->get($sort_field), 0, 1);
                if (!in_array($char, $this->nav_list)) {
                    $char = $this->nav_special;
                }
                if ($this->getIndex() != $char) {
                    continue;
                }
            }

            $current = App::core()->blog()->settings()->get('system')->get('theme') == $id && $this->modules()->hasModule($id);
            $distrib = $this->modules()->isDistributedModule($id) ? ' dc-box' : '';
            $line    = '<div class="box ' . ($current ? 'medium current-theme' : 'theme') . $distrib . '">';

            if (in_array('name', $cols) && !$current) {
                $line .= '<h4 class="module-name">';

                if (in_array('checkbox', $cols)) {
                    $line .= '<label for="' . Html::escapeHTML($this->list_id) . '_modules_' . Html::escapeHTML($id) . '">' .
                    Form::checkbox(['modules[' . $count . ']', Html::escapeHTML($this->list_id) . '_modules_' . Html::escapeHTML($id)], Html::escapeHTML($id)) .
                    Html::escapeHTML($module->name()) .
                        '</label>';
                } else {
                    $line .= Form::hidden(['modules[' . $count . ']'], Html::escapeHTML($id)) .
                    Html::escapeHTML($module->name());
                }

                $line .= App::core()->nonce()->form() .
                    '</h4>';
            }

            // Display score only for debug purpose
            if (in_array('score', $cols) && $this->getSearch() !== null && !App::core()->production()) {
                $line .= '<p class="module-score debug">' . sprintf(__('Score: %s'), $module->score()) . '</p>';
            }

            if (in_array('screenshot', $cols)) {
                $sshot = '';
                // Screenshot from url
                if (preg_match('#^http(s)?://#', $module->screenshot())) {
                    $sshot = $module->screenshot();
                // Screenshot from installed module
                } elseif (file_exists($module->root() . '/screenshot.jpg')) {
                    $sshot = '?df=Theme/' . $id . '/screenshot.jpg';
                }
                // Default screenshot
                if (!$sshot) {
                    $sshot = '?df=images/noscreenshot.png';
                }

                $line .= '<div class="module-sshot"><img src="' . $sshot . '" loading="lazy" alt="' .
                sprintf(__('%s screenshot.'), Html::escapeHTML($module->name())) . '" /></div>';
            }

            $line .= $current ? '' : '<details><summary>' . __('Details') . '</summary>';
            $line .= '<div class="module-infos">';

            if (in_array('name', $cols) && $current) {
                $line .= '<h4 class="module-name">';

                if (in_array('checkbox', $cols)) {
                    $line .= '<label for="' . Html::escapeHTML($this->list_id) . '_modules_' . Html::escapeHTML($id) . '">' .
                    Form::checkbox(['modules[' . $count . ']', Html::escapeHTML($this->list_id) . '_modules_' . Html::escapeHTML($id)], Html::escapeHTML($id)) .
                    Html::escapeHTML($module->name()) .
                        '</label>';
                } else {
                    $line .= Form::hidden(['modules[' . $count . ']'], Html::escapeHTML($id)) .
                    Html::escapeHTML($module->name());
                }

                $line .= '</h4>';
            }

            $line .= '<p>';

            if (in_array('description', $cols)) {
                $line .= '<span class="module-desc">' . Html::escapeHTML(__($module->description())) . '</span> ';
            }

            if (in_array('author', $cols)) {
                $line .= '<span class="module-author">' . sprintf(__('by %s'), Html::escapeHTML($module->author())) . '</span> ';
            }

            if (in_array('version', $cols)) {
                $line .= '<span class="module-version">' . sprintf(__('version %s'), Html::escapeHTML($module->version())) . '</span> ';
            }

            if (in_array('current_version', $cols)) {
                $line .= '<span class="module-current-version">' . sprintf(__('(current version %s)'), Html::escapeHTML($module->current_version())) . '</span> ';
            }

            if (in_array('parent', $cols) && !empty($module->parent())) {
                if ($this->modules()->hasModule($module->parent())) {
                    $line .= '<span class="module-parent-ok">' . sprintf(__('(built on "%s")'), Html::escapeHTML($module->parent())) . '</span> ';
                } else {
                    $line .= '<span class="module-parent-missing">' . sprintf(__('(requires "%s")'), Html::escapeHTML($module->parent())) . '</span> ';
                }
            }

            if (in_array('repository', $cols) && App::core()->config()->get('store_allow_repo')) {
                $line .= '<span class="module-repository">' . (!empty($module->repository()) ? __('Third-party repository') : __('Official repository')) . '</span> ';
            }

            $has_details = in_array('details', $cols) && !empty($module->details());
            $has_support = in_array('support', $cols) && !empty($module->support());
            if ($has_details || $has_support) {
                $line .= '<span class="mod-more">';

                if ($has_details) {
                    $line .= '<a class="module-details" href="' . $module->details() . '">' . __('Details') . '</a>';
                }

                if ($has_support) {
                    $line .= ' - <a class="module-support" href="' . $module->support() . '">' . __('Support') . '</a>';
                }

                $line .= '</span>';
            }

            $line .= '</p>' .
                '</div>';
            $line .= '<div class="module-actions">';

            // Plugins actions
            if ($current) {
                // _GET actions
                if (file_exists($module->root() . '/Public/resources/style.css')
                    || file_exists($module->root() . '/Common/resources/style.css')
                ) {
                    $line .= '<p><a href="' . App::core()->blog()->getURLFor('resources', 'Theme/' . $id . '/style.css') . '">' . __('View stylesheet') . '</a></p>';
                }

                $line .= '<div class="current-actions">';

                $config = is_subclass_of(
                    'Dotclear\\' . $module->type() . '\\' . $id . '\\Admin\\Config',
                    'Dotclear\\Modules\\ModuleConfig'
                );
                if ($config) {
                    $params = ['module' => $id, 'conf' => '1'];
                    if (!$module->standaloneConfig()) {
                        $params['redir'] = $this->modules()->getModuleURL($id);
                    }
                    $line .= '<p><a href="' . $this->modules()->getModulesURL($params) . '" class="button submit">' . __('Configure theme') . '</a></p>';
                }

                // --BEHAVIOR-- adminCurrentThemeDetails
                $line .= App::core()->behavior()->call('adminCurrentThemeDetails', $module);

                $line .= '</div>';
            }

            // _POST actions
            if (!empty($actions)) {
                $line .= '<p class="module-post-actions">' . implode(' ', $this->getActions($id, $module, $actions)) . '</p>';
            }

            $line .= '</div>';
            $line .= $current ? '' : '</details>';

            $line .= '</div>';

            ++$count;

            $res = $current ? $line . $res : $res . $line;
        }

        echo $res .
            '</div>';

        if (!$count && null === $this->getSearch()) {
            echo '<p class="message">' . __('No themes matched your search.') . '</p>';
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

    protected function getActions(string $id, ModuleDefine $module, array $actions): array
    {
        $submits = [];

        if (App::core()->blog()->settings()->get('system')->get('theme') != $id) {
            // Select theme to use on curent blog
            if (in_array('select', $actions)) {
                $submits[] = '<input type="submit" name="select[' . Html::escapeHTML($id) . ']" value="' . __('Use this one') . '" />';
            }
        } elseif (App::core()->production()) {
            // Currently selected theme
            if ($pos = array_search('delete', $actions, true)) {
                // Remove 'delete' action
                unset($actions[$pos]);
            }
            if ($pos = array_search('deactivate', $actions, true)) {
                // Remove 'deactivate' action
                unset($actions[$pos]);
            }
        }

        if ($this->modules()->isDistributedModule($id) && ($pos = array_search('delete', $actions, true))) {
            // Remove 'delete' action for officially distributed themes
            unset($actions[$pos]);
        }

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

    protected function getGlobalActions(array $actions, bool $with_selection = false): array
    {
        $submits = [];

        foreach ($actions as $action) {
            switch ($action) {
                // Update (from store)
                case 'update':
                    if (App::core()->user()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update" value="' . (
                            $with_selection ?
                            __('Update selected themes') :
                            __('Update all themes from this list')
                        ) . '" />' . App::core()->nonce()->form();
                    }

                    break;
                // Behavior
                case 'behavior':
                    // --BEHAVIOR-- adminModulesListGetGlobalActions
                    $tmp = App::core()->behavior()->call('adminModulesListGetGlobalActions', $this);

                    if (!empty($tmp)) {
                        $submits[] = $tmp;
                    }

                    break;
            }
        }

        return $submits;
    }

    public function doActions(): void
    {
        if (empty($_POST) || !empty($_REQUEST['conf'])) {
            return;
        }

        $modules = !empty($_POST['modules']) && is_array($_POST['modules']) ? array_values($_POST['modules']) : [];

        if (!empty($_POST['select'])) {
            // Can select only one theme at a time!
            if (is_array($_POST['select'])) {
                $modules = array_keys($_POST['select']);
                $id      = $modules[0];

                if (!$this->modules()->hasModule($id)) {
                    throw new ModuleException(__('No such theme.'));
                }

                App::core()->blog()->settings()->get('system')->put('theme', $id);
                App::core()->blog()->triggerBlog();

                $module = $this->modules()->getModule($id);
                App::core()->notice()->addSuccessNotice(sprintf(__('Theme %s has been successfully selected.'), Html::escapeHTML($module->name())));
                Http::redirect($this->getURL() . '#themes');
            }
        } else {
            if (!$this->isWritablePath()) {
                return;
            }

            if (App::core()->user()->isSuperAdmin() && !empty($_POST['activate'])) {
                if (is_array($_POST['activate'])) {
                    $modules = array_keys($_POST['activate']);
                }

                $list = $this->modules()->getDisabledModules();
                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    // --BEHAVIOR-- themeBeforeActivate
                    App::core()->behavior()->call('themeBeforeActivate', $id);

                    $this->modules()->activateModule($id);

                    // --BEHAVIOR-- themeAfterActivate
                    App::core()->behavior()->call('themeAfterActivate', $id);

                    ++$count;
                }

                App::core()->notice()->addSuccessNotice(
                    __('Theme has been successfully activated.', 'Themes have been successuflly activated.', $count)
                );
                Http::redirect($this->getURL());
            } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['deactivate'])) {
                if (is_array($_POST['deactivate'])) {
                    $modules = array_keys($_POST['deactivate']);
                }

                $list = $this->modules()->getModules();
                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
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

                    // --BEHAVIOR-- themeBeforeDeactivate
                    App::core()->behavior()->call('themeBeforeDeactivate', $module);

                    $this->modules()->deactivateModule($id);

                    // --BEHAVIOR-- themeAfterDeactivate
                    App::core()->behavior()->call('themeAfterDeactivate', $module);

                    ++$count;
                }

                if ($failed) {
                    App::core()->notice()->addWarningNotice(__('Some themes have not been deactivated.'));
                } else {
                    App::core()->notice()->addSuccessNotice(
                        __('Theme has been successfully deactivated.', 'Themes have been successuflly deactivated.', $count)
                    );
                }
                Http::redirect($this->getURL());
            } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['clone'])) {
                if (is_array($_POST['clone'])) {
                    $modules = array_keys($_POST['clone']);
                }

                $count = 0;
                foreach ($modules as $id) {
                    if (!$this->modules()->hasModule($id)) {
                        throw new ModuleException(__('No such theme.'));
                    }

                    // --BEHAVIOR-- themeBeforeClone
                    App::core()->behavior()->call('themeBeforeClone', $id);

                    $this->cloneModule($id);

                    // --BEHAVIOR-- themeAfterClone
                    App::core()->behavior()->call('themeAfterClone', $id);

                    ++$count;
                }

                App::core()->notice()->addSuccessNotice(
                    __('Theme has been successfully cloned.', 'Themes have been successuflly cloned.', $count)
                );
                Http::redirect($this->getURL());
            } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['delete'])) {
                if (is_array($_POST['delete'])) {
                    $modules = array_keys($_POST['delete']);
                }

                $list = $this->modules()->getDisabledModules();

                $failed = false;
                $count  = 0;
                foreach ($modules as $id) {
                    if (!isset($list[$id])) {
                        if (!$this->modules()->hasModule($id)) {
                            throw new ModuleException(__('No such theme.'));
                        }

                        $module = $this->modules()->getModule($id);

                        if (!$this->isDeletablePath($module->root())) {
                            $failed = true;

                            continue;
                        }

                        // --BEHAVIOR-- themeBeforeDelete
                        App::core()->behavior()->call('themeBeforeDelete', $module);

                        $this->modules()->deleteModule($id);

                        // --BEHAVIOR-- themeAfterDelete
                        App::core()->behavior()->call('themeAfterDelete', $module);
                    } else {
                        $this->modules()->deleteModule($id, true);
                    }

                    ++$count;
                }

                if (!$count && $failed) {
                    throw new ModuleException(__("You don't have permissions to delete this theme."));
                }
                if ($failed) {
                    App::core()->notice()->addWarningNotice(__('Some themes have not been delete.'));
                } else {
                    App::core()->notice()->addSuccessNotice(
                        __('Theme has been successfully deleted.', 'Themes have been successuflly deleted.', $count)
                    );
                }
                Http::redirect($this->getURL());
            } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['install'])) {
                if (is_array($_POST['install'])) {
                    $modules = array_keys($_POST['install']);
                }

                $list = $this->modules()->store()->get();

                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    $dest = $this->getPath() . '/' . basename($module->file());

                    // --BEHAVIOR-- themeBeforeAdd
                    App::core()->behavior()->call('themeBeforeAdd', $module);

                    $this->modules()->store()->process($module->file(), $dest);

                    // --BEHAVIOR-- themeAfterAdd
                    App::core()->behavior()->call('themeAfterAdd', $module);

                    ++$count;
                }

                App::core()->notice()->addSuccessNotice(
                    __('Theme has been successfully installed.', 'Themes have been successfully installed.', $count)
                );
                Http::redirect($this->getURL());
            } elseif (App::core()->user()->isSuperAdmin() && !empty($_POST['update'])) {
                if (is_array($_POST['update'])) {
                    $modules = array_keys($_POST['update']);
                }

                $list = $this->modules()->store()->get(true);
                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $module) {
                    if (!in_array($module->id(), $modules)) {
                        continue;
                    }

                    $dest = $module->root() . '/../' . basename($module->file());

                    // --BEHAVIOR-- themeBeforeUpdate
                    App::core()->behavior()->call('themeBeforeUpdate', $module);

                    $this->modules()->store()->process($module->file(), $dest);

                    // --BEHAVIOR-- themeAfterUpdate
                    App::core()->behavior()->call('themeAfterUpdate', $module);

                    ++$count;
                }

                $tab = $count && count($list) == $count ? '#themes' : '#update';

                App::core()->notice()->addSuccessNotice(
                    __('Theme has been successfully updated.', 'Themes have been successfully updated.', $count)
                );
                Http::redirect($this->getURL() . $tab);
            }

            // Manual actions
            elseif (!empty($_POST['upload_pkg']) && !empty($_FILES['pkg_file'])
                || !empty($_POST['fetch_pkg']) && !empty($_POST['pkg_url'])) {
                if (empty($_POST['your_pwd']) || !App::core()->user()->checkPassword($_POST['your_pwd'])) {
                    throw new ModuleException(__('Password verification failed'));
                }

                if (!empty($_POST['upload_pkg'])) {
                    Files::uploadStatus($_FILES['pkg_file']);

                    $dest = $this->getPath() . '/' . $_FILES['pkg_file']['name'];
                    if (!move_uploaded_file($_FILES['pkg_file']['tmp_name'], $dest)) {
                        throw new ModuleException(__('Unable to move uploaded file.'));
                    }
                } else {
                    $url  = urldecode($_POST['pkg_url']);
                    $dest = $this->getPath() . '/' . basename($url);
                    $this->modules()->store()->download($url, $dest);
                }

                // --BEHAVIOR-- themeBeforeAdd
                App::core()->behavior()->call('themeBeforeAdd', null);

                $ret_code = $this->modules()->store()->install($dest);

                // --BEHAVIOR-- themeAfterAdd
                App::core()->behavior()->call('themeAfterAdd', null);

                App::core()->notice()->addSuccessNotice(
                    2 == $ret_code ?
                    __('The theme has been successfully updated.') :
                    __('The theme has been successfully installed.')
                );
                Http::redirect($this->getURL() . '#themes');
            } else {
                // --BEHAVIOR-- adminModulesListDoActions
                App::core()->behavior()->call('adminModulesListDoActions', $this, $modules, 'theme');
            }
        }
    }

    public function cloneModule(string $id): void
    {
        // Check destination path
        $root = $this->path;
        if (!is_string($root) || !is_dir($root) || !is_writable($root)) {
            throw new ModuleException(__('Themes folder unreachable'));
        }
        if (substr($root, -1) != '/') {
            $root .= '/';
        }
        if (false === ($d = @dir($root))) {
            throw new ModuleException(__('Themes folder unreadable'));
        }

        $module = $this->modules()->getModule($id);
        if (!$module) {
            throw new ModuleException('Theme is unknown');
        }
        $counter = 0;
        $new_id  = sprintf('%sClone', $module->id());
        $new_dir = sprintf('%sClone', $root . $module->id());
        while (is_dir($new_dir) || $this->modules()->hasModule($new_id)) {
            ++$counter;
            $new_id  = sprintf('%sClone%s', $module->id(), $counter);
            $new_dir = sprintf('%sClone%s', $root . $module->id(), $counter);
        }
        $old_ns  = 'Dotclear\\' . $module->type() . '\\' . $module->id();
        $new_ns  = 'Dotclear\\' . $module->type() . '\\' . $new_id;

        if (!is_dir($new_dir)) {
            try {
                // Create destination folder named $new_dir in themes folder
                Files::makeDir($new_dir, false);
                // Copy files
                $content = Files::getDirList($module->root());
                foreach ($content['dirs'] as $dir) {
                    $rel = substr($dir, strlen($module->root()));
                    if ('' !== $rel) {
                        Files::makeDir($new_dir . $rel);
                    }
                }
                foreach ($content['files'] as $file) {
                    $rel = substr($file, strlen($module->root()));
                    copy($file, $new_dir . $rel);

                    // replace only full namespace
                    if ('.php' == substr($rel, -4)) {
                        $buf = file_get_contents($new_dir . $rel);
                        $buf = str_replace($old_ns, $new_ns, $buf);
                        file_put_contents($new_dir . $rel, $buf);
                    // replace all reference that look like module id
                    } elseif ('define.xml' == substr($rel, -10)) {
                        $buf = file_get_contents($new_dir . $rel);
                        $buf = str_replace($module->id(), $new_id, $buf);
                        file_put_contents($new_dir . $rel, $buf);
                    }
                    // @todo Find what to replace in .po and .js files
                }
            } catch (Exception $e) {
                Files::deltree($new_dir);

                throw new ModuleException($e->getMessage());
            }
        } else {
            throw new ModuleException(__('Destination folder already exist'));
        }
    }
}
