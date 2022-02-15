<?php
/**
 * @class Dotclear\Module\Theme\Admin\ModulesTheme
 *
 * @package Dotclear
 * @subpackage Module
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme\Admin;

use Dotclear\Exception\ModuleException;
use Dotclear\File\Files;
use Dotclear\File\Path;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Module\AbstractDefine;
use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Theme\TraitModulesTheme;
use Dotclear\Network\Http;

class ModulesTheme extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesTheme;

    protected function register(): bool
    {
        dotclear()->adminurl()->register(
            'admin.blog.theme',
            root_ns('Module', 'Theme', 'Admin', 'PageTheme')
        );
        dotclear()->summary()->register(
            'Blog',
            __('Blog appearance'),
            'admin.blog.theme',
            ['images/menu/themes.svg', 'images/menu/themes-dark.svg'],
            dotclear()->auth()->check('admin', dotclear()->blog()->id)
        );
        dotclear()->favorite()->register('blog_theme', [
            'title'       => __('Blog appearance'),
            'url'         => dotclear()->adminurl()->get('admin.blog.theme'),
            'small-icon'  => ['images/menu/themes.svg', 'images/menu/themes-dark.svg'],
            'large-icon'  => ['images/menu/themes.svg', 'images/menu/themes-dark.svg'],
            'permissions' => 'admin'
        ]);

        return dotclear()->adminurl()->called() == 'admin.blog.theme';
    }

    public function getModulesURL(array $param = []): string
    {
        return dotclear()->adminurl()->get('admin.blog.theme', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return dotclear()->adminurl()->get('admin.blog.theme', array_merge(['id' => $id], $param));
    }

    public function displayData(array $cols = ['name', 'version', 'description'], array $actions = [], bool $nav_limit = false): AbstractModules
    {
        echo
        '<form action="' . $this->getURL() . '" method="post" class="modules-form-actions">' .
        '<div id="' . Html::escapeHTML($this->list_id) . '" class="modules' . (in_array('expander', $cols) ? ' expandable' : '') . ' one-box">';

        $sort_field = $this->getSort();

        # Sort modules by id
        $modules = $this->getSearch() === null ? self::sortModules($this->data, $sort_field, $this->sort_asc) : $this->data;

        $res   = '';
        $count = 0;
        foreach ($modules as $id => $module) {
            # Show only requested modules
            if ($nav_limit && $this->getSearch() === null) {
                $properties = $module->properties();
                $char = substr($properties[$sort_field], 0, 1);
                if (!in_array($char, $this->nav_list)) {
                    $char = $this->nav_special;
                }
                if ($this->getIndex() != $char) {
                    continue;
                }
            }

            $current = dotclear()->blog()->settings->system->theme == $id && $this->hasModule($id);
            $distrib = $this->isDistributedModule($id) ? ' dc-box' : '';
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

                $line .= dotclear()->nonce()->form() .
                    '</h4>';
            }

            # Display score only for debug purpose
            if (in_array('score', $cols) && $this->getSearch() !== null && dotclear()->config()->run_level >= DOTCLEAR_RUN_DEBUG) {   // @phpstan-ignore-line
                $line .= '<p class="module-score debug">' . sprintf(__('Score: %s'), $module->sdotclear()) . '</p>';
            }

            if (in_array('screenshot', $cols)) {
                $sshot = '';
                # Screenshot from url
                if (preg_match('#^http(s)?://#', $module->screenshot())) {
                    $sshot = $module->screenshot();
                # Screenshot from installed module
                } else {
                    foreach($this->getModulesPath() as $psshot) {
                        if (file_exists($psshot . '/' . $id . '/screenshot.jpg')) {
                            $sshot = '?mf=Theme/' . $id . '/screenshot.jpg';
                            break;
                        }
                    }
                }
                # Default screenshot
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
                if ($this->hasModule($module->parent())) {
                    $line .= '<span class="module-parent-ok">' . sprintf(__('(built on "%s")'), Html::escapeHTML($module->parent())) . '</span> ';
                } else {
                    $line .= '<span class="module-parent-missing">' . sprintf(__('(requires "%s")'), Html::escapeHTML($module->parent())) . '</span> ';
                }
            }

            if (in_array('repository', $cols) && dotclear()->config()->store_allow_repo) {
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

            # Plugins actions
            if ($current) {

                # _GET actions
                foreach($this->getModulesPath() as $psstyle) {
                    if (file_exists($psstyle . '/' . $id . '/files/style.css')) {
                        $line .= '<p><a href="' . dotclear()->blog()->getQmarkURL() . 'mf=Theme/' . $id . '/files/style.css">' . __('View stylesheet') . '</a></p>';
                        break;
                    }
                }

                $line .= '<div class="current-actions">';

                $config_class = root_ns($module->type(), $id, 'Admin', 'Config');
                if (is_subclass_of($config_class, 'Dotclear\\Module\\AbstractConfig')) {
                    $params = ['module' => $id, 'conf' => '1'];
                    if (!$module->standaloneConfig()) {
                        $params['redir'] = $this->getModuleURL($id);
                    }
                    $line .= '<p><a href="' . $this->getModulesURL($params) . '" class="button submit">' . __('Configure theme') . '</a></p>';
                }

                # --BEHAVIOR-- adminCurrentThemeDetails
                $line .= dotclear()->behavior()->call('adminCurrentThemeDetails', $module);

                $line .= '</div>';
            }

            # _POST actions
            if (!empty($actions)) {
                $line .= '<p class="module-post-actions">' . implode(' ', $this->getActions($id, $module, $actions)) . '</p>';
            }

            $line .= '</div>';
            $line .= $current ? '' : '</details>';

            $line .= '</div>';

            $count++;

            $res = $current ? $line . $res : $res . $line;
        }

        echo
            $res .
            '</div>';

        if (!$count && $this->getSearch() === null) {
            echo
            '<p class="message">' . __('No themes matched your search.') . '</p>';
        } elseif ((in_array('checkbox', $cols) || $count > 1) && !empty($actions) && dotclear()->auth()->isSuperAdmin()) {
            $buttons = $this->getGlobalActions($actions, in_array('checkbox', $cols));

            if (!empty($buttons)) {
                if (in_array('checkbox', $cols)) {
                    echo
                        '<p class="checkboxes-helpers"></p>';
                }
                echo '<div>' . implode(' ', $buttons) . '</div>';
            }
        }

        echo
            '</form>';

        return $this;
    }

    protected function getActions(string $id, AbstractDefine $module, array $actions): array
    {
        $submits = [];

        dotclear()->blog()->settings->addNamespace('system');
        if ($id != dotclear()->blog()->settings->system->theme) {

            # Select theme to use on curent blog
            if (in_array('select', $actions)) {
                $submits[] = '<input type="submit" name="select[' . Html::escapeHTML($id) . ']" value="' . __('Use this one') . '" />';
            }
        } elseif (dotclear()->config()->run_level < DOTCLEAR_RUN_DEBUG) {
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

        if ($this->isDistributedModule($id) && ($pos = array_search('delete', $actions, true))) {
            // Remove 'delete' action for officially distributed themes
            unset($actions[$pos]);
        }

        # Use loop to keep requested order
        foreach ($actions as $action) {
            switch ($action) {

                # Deactivate
                case 'activate':
                    if (dotclear()->auth()->isSuperAdmin() && $module->writable() && empty($module->depMissing())) {
                        $submits[] = '<input type="submit" name="activate[' . Html::escapeHTML($id) . ']" value="' . __('Activate') . '" />';
                    }

                    break;

                # Activate
                case 'deactivate':
                    if (dotclear()->auth()->isSuperAdmin() && $module->writable() && empty($module->depChildren())) {
                        $submits[] = '<input type="submit" name="deactivate[' . Html::escapeHTML($id) . ']" value="' . __('Deactivate') . '" class="reset" />';
                    }

                    break;

                # Delete
                case 'delete':
                    if (dotclear()->auth()->isSuperAdmin() && $this->isDeletablePath($module->root()) && empty($module->depChildren())) {
                        $dev       = !preg_match('!^' . $this->path_pattern . '!', $module->root()) && dotclear()->config()->run_level >= DOTCLEAR_RUN_DEVELOPMENT ? ' debug' : '';
                        $submits[] = '<input type="submit" class="delete ' . $dev . '" name="delete[' . Html::escapeHTML($id) . ']" value="' . __('Delete') . '" />';
                    }

                    break;

                # Clone
                case 'clone':
                    if (dotclear()->auth()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" class="button clone" name="clone[' . Html::escapeHTML($id) . ']" value="' . __('Clone') . '" />';
                    }

                    break;

                # Install (from store)
                case 'install':
                    if (dotclear()->auth()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="install[' . Html::escapeHTML($id) . ']" value="' . __('Install') . '" />';
                    }

                    break;

                # Update (from store)
                case 'update':
                    if (dotclear()->auth()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update[' . Html::escapeHTML($id) . ']" value="' . __('Update') . '" />';
                    }

                    break;

                # Behavior
                case 'behavior':

                    # --BEHAVIOR-- adminModulesListGetActions
                    $tmp = dotclear()->behavior()->call('adminModulesListGetActions', $this, $id, $module);

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

                # Update (from store)
                case 'update':

                    if (dotclear()->auth()->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update" value="' . (
                            $with_selection ?
                            __('Update selected themes') :
                            __('Update all themes from this list')
                        ) . '" />' . dotclear()->nonce()->form();
                    }

                    break;

                # Behavior
                case 'behavior':

                    # --BEHAVIOR-- adminModulesListGetGlobalActions
                    $tmp = dotclear()->behavior()->call('adminModulesListGetGlobalActions', $this);

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

            # Can select only one theme at a time!
            if (is_array($_POST['select'])) {
                $modules = array_keys($_POST['select']);
                $id      = $modules[0];

                if (!$this->hasModule($id)) {
                    throw new ModuleException(__('No such theme.'));
                }

                dotclear()->blog()->settings->addNamespace('system');
                dotclear()->blog()->settings->system->put('theme', $id);
                dotclear()->blog()->triggerBlog();

                $module = $this->getModule($id);
                dotclear()->notice()->addSuccessNotice(sprintf(__('Theme %s has been successfully selected.'), Html::escapeHTML($module->name())));
                Http::redirect($this->getURL() . '#themes');
            }
        } else {
            if (!$this->isWritablePath()) {
                return;
            }

            if (dotclear()->auth()->isSuperAdmin() && !empty($_POST['activate'])) {
                if (is_array($_POST['activate'])) {
                    $modules = array_keys($_POST['activate']);
                }

                $list = $this->getDisabledModules();
                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    # --BEHAVIOR-- themeBeforeActivate
                    dotclear()->behavior()->call('themeBeforeActivate', $id);

                    $this->activateModule($id);

                    # --BEHAVIOR-- themeAfterActivate
                    dotclear()->behavior()->call('themeAfterActivate', $id);

                    $count++;
                }

                dotclear()->notice()->addSuccessNotice(
                    __('Theme has been successfully activated.', 'Themes have been successuflly activated.', $count)
                );
                Http::redirect($this->getURL());
            } elseif (dotclear()->auth()->isSuperAdmin() && !empty($_POST['deactivate'])) {
                if (is_array($_POST['deactivate'])) {
                    $modules = array_keys($_POST['deactivate']);
                }

                $list = $this->getModules();
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

                    # --BEHAVIOR-- themeBeforeDeactivate
                    dotclear()->behavior()->call('themeBeforeDeactivate', $module);

                    $this->deactivateModule($id);

                    # --BEHAVIOR-- themeAfterDeactivate
                    dotclear()->behavior()->call('themeAfterDeactivate', $module);

                    $count++;
                }

                if ($failed) {
                    dotclear()->notice()->addWarningNotice(__('Some themes have not been deactivated.'));
                } else {
                    dotclear()->notice()->addSuccessNotice(
                        __('Theme has been successfully deactivated.', 'Themes have been successuflly deactivated.', $count)
                    );
                }
                Http::redirect($this->getURL());
            } elseif (dotclear()->auth()->isSuperAdmin() && !empty($_POST['clone'])) {
                if (is_array($_POST['clone'])) {
                    $modules = array_keys($_POST['clone']);
                }

                $count = 0;
                foreach ($modules as $id) {
                    if (!$this->hasModule($id)) {
                        throw new ModuleException(__('No such theme.'));
                    }

                    # --BEHAVIOR-- themeBeforeClone
                    dotclear()->behavior()->call('themeBeforeClone', $id);

                    $this->cloneModule($id);

                    # --BEHAVIOR-- themeAfterClone
                    dotclear()->behavior()->call('themeAfterClone', $id);

                    $count++;
                }

                dotclear()->notice()->addSuccessNotice(
                    __('Theme has been successfully cloned.', 'Themes have been successuflly cloned.', $count)
                );
                Http::redirect($this->getURL());
            } elseif (dotclear()->auth()->isSuperAdmin() && !empty($_POST['delete'])) {
                if (is_array($_POST['delete'])) {
                    $modules = array_keys($_POST['delete']);
                }

                $list = $this->getDisabledModules();

                $failed = false;
                $count  = 0;
                foreach ($modules as $id) {
                    if (!isset($list[$id])) {
                        if (!$this->hasModule($id)) {
                            throw new ModuleException(__('No such theme.'));
                        }

                        $module = $this->getModule($id);

                        if (!$this->isDeletablePath($module->root())) {
                            $failed = true;

                            continue;
                        }

                        # --BEHAVIOR-- themeBeforeDelete
                        dotclear()->behavior()->call('themeBeforeDelete', $module);

                        $this->deleteModule($id);

                        # --BEHAVIOR-- themeAfterDelete
                        dotclear()->behavior()->call('themeAfterDelete', $module);
                    } else {
                        $this->deleteModule($id, true);
                    }

                    $count++;
                }

                if (!$count && $failed) {
                    throw new ModuleException(__("You don't have permissions to delete this theme."));
                } elseif ($failed) {
                    dotclear()->notice()->addWarningNotice(__('Some themes have not been delete.'));
                } else {
                    dotclear()->notice()->addSuccessNotice(
                        __('Theme has been successfully deleted.', 'Themes have been successuflly deleted.', $count)
                    );
                }
                Http::redirect($this->getURL());
            } elseif (dotclear()->auth()->isSuperAdmin() && !empty($_POST['install'])) {
                if (is_array($_POST['install'])) {
                    $modules = array_keys($_POST['install']);
                }

                $list = $this->store->get();

                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    $dest = $this->getPath() . '/' . basename($module->file());

                    # --BEHAVIOR-- themeBeforeAdd
                    dotclear()->behavior()->call('themeBeforeAdd', $module);

                    $this->store->process($module->file(), $dest);

                    # --BEHAVIOR-- themeAfterAdd
                    dotclear()->behavior()->call('themeAfterAdd', $module);

                    $count++;
                }

                dotclear()->notice()->addSuccessNotice(
                    __('Theme has been successfully installed.', 'Themes have been successfully installed.', $count)
                );
                Http::redirect($this->getURL());
            } elseif (dotclear()->auth()->isSuperAdmin() && !empty($_POST['update'])) {
                if (is_array($_POST['update'])) {
                    $modules = array_keys($_POST['update']);
                }

                $list = $this->store->get(true);
                if (empty($list)) {
                    throw new ModuleException(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $module) {
                    if (!in_array($module->id(), $modules)) {
                        continue;
                    }

                    $dest = $module->root() . '/../' . basename($module->file());

                    # --BEHAVIOR-- themeBeforeUpdate
                    dotclear()->behavior()->call('themeBeforeUpdate', $module);

                    $this->store->process($module->file(), $dest);

                    # --BEHAVIOR-- themeAfterUpdate
                    dotclear()->behavior()->call('themeAfterUpdate', $module);

                    $count++;
                }

                $tab = $count && $count == count($list) ? '#themes' : '#update';

                dotclear()->notice()->addSuccessNotice(
                    __('Theme has been successfully updated.', 'Themes have been successfully updated.', $count)
                );
                Http::redirect($this->getURL() . $tab);
            }

            # Manual actions
            elseif (!empty($_POST['upload_pkg']) && !empty($_FILES['pkg_file'])
                || !empty($_POST['fetch_pkg']) && !empty($_POST['pkg_url'])) {
                if (empty($_POST['your_pwd']) || !dotclear()->auth()->checkPassword($_POST['your_pwd'])) {
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
                    $this->store->download($url, $dest);
                }

                # --BEHAVIOR-- themeBeforeAdd
                dotclear()->behavior()->call('themeBeforeAdd', null);

                $ret_code = $this->store->install($dest);

                # --BEHAVIOR-- themeAfterAdd
                dotclear()->behavior()->call('themeAfterAdd', null);

                dotclear()->notice()->addSuccessNotice(
                    $ret_code == 2 ?
                    __('The theme has been successfully updated.') :
                    __('The theme has been successfully installed.')
                );
                Http::redirect($this->getURL() . '#themes');
            } else {

                # --BEHAVIOR-- adminModulesListDoActions
                dotclear()->behavior()->call('adminModulesListDoActions', $this, $modules, 'theme');
            }
        }
    }

    public function cloneModule(string $id): void
    {
        # Check destination path
        $root = $this->path;
        if (!is_dir($root) || !is_writable($root)) {
            throw new ModuleException(__('Themes folder unreachable'));
        }
        if (substr($root, -1) != '/') {
            $root .= '/';
        }
        if (($d = @dir($root)) === false) {
            throw new ModuleException(__('Themes folder unreadable'));
        }

        $module = $this->getModule($id);
        if (!$module) {
            throw new ModuleException('Theme is unknown');
        }
        $counter = 0;
        $new_id  = sprintf('%sClone', $module->id());
        $new_dir = sprintf('%sClone', $root . $module->id());
        while (is_dir($new_dir)) {
            $counter++;
            $new_id  = sprintf('%sClone%s', $module->id(), $counter);
            $new_dir = sprintf('%sClone%s', $root . $module->id(), $counter);
        }

        if (!is_dir($new_dir)) {
            try {
                // Create destination folder named $new_dir in themes folder
                Files::makeDir($new_dir, false);
                // Copy files
                $content = Files::getDirList($module->root());
                foreach ($content['dirs'] as $dir) {
                    $rel = substr($dir, strlen($module->root()));
                    if ($rel !== '') {
                        Files::makeDir($new_dir . $rel);
                    }
                }
                foreach ($content['files'] as $file) {
                    $rel = substr($file, strlen($module->root()));
                    copy($file, $new_dir . $rel);

                    if (in_array(substr($rel, -4), ['.xml', '.php'])) {
                        $buf = file_get_contents($new_dir . $rel);
                        $buf = str_replace($module->id(), $new_id, $buf);
                        file_put_contents($new_dir . $rel, $buf);
                    }
                }
            } catch (\Exception $e) {
                Files::deltree($new_dir);

                throw new ModuleException($e->getMessage());
            }
        } else {
            throw new ModuleException(__('Destination folder already exist'));
        }
    }
}
