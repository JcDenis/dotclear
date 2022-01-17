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

use Dotclear\Module\AbstractModules;
use Dotclear\Module\TraitModulesAdmin;
use Dotclear\Module\Theme\TraitModulesTheme;
use Dotclear\Module\AbstractDefine;

use Dotclear\Html\Html;
use Dotclear\Html\Form;

class ModulesTheme extends AbstractModules
{
    use TraitModulesAdmin, TraitModulesTheme;

    public function getModulesURL(array $param = []): string
    {
        return $this->core->adminurl->get('admin.blog.theme', $param);
    }

    public function getModuleURL(string $id, array $param = []): string
    {
        return $this->core->adminurl->get('admin.blog.theme', array_merge(['id' => $id], $param));
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

            $current = $this->core->blog->settings->system->theme == $id && $this->hasModule($id);
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

                $line .= $this->core->formNonce() .
                    '</h4>';
            }

            # Display score only for debug purpose
            if (in_array('score', $cols) && $this->getSearch() !== null && DOTCLEAR_MODE_DEBUG) {   // @phpstan-ignore-line
                $line .= '<p class="module-score debug">' . sprintf(__('Score: %s'), $module->score()) . '</p>';
            }

            if (in_array('screenshot', $cols)) {
                # Screenshot from url
                if (preg_match('#^http(s)?://#', $module->screenshot())) {
                    $sshot = $module->screenshot();
                }
                # Screenshot from installed module
                elseif (file_exists($this->core->blog->themes_path . '/' . $id . '/screenshot.jpg')) {
                    $sshot = '?mf=Theme/' . $id . '/screenshot.jpg'; //$this->getURL('shot=' . rawurlencode($id));
                }
                # Default screenshot
                else {
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

            if (in_array('repository', $cols) && DOTCLEAR_ALLOW_REPOSITORIES) {   // @phpstan-ignore-line
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

                # _GET actions //! check this
                if (file_exists(Path::real($this->core->blog->themes_path . '/' . $id) . '/style.css')) {
                    $theme_url = preg_match('#^http(s)?://#', $this->core->blog->settings->system->themes_url) ?
                    Http::concatURL($this->core->blog->settings->system->themes_url, '/' . $id) :
                    Http::concatURL($this->core->blog->url, $this->core->blog->settings->system->themes_url . '/' . $id);
                    $line .= '<p><a href="' . $theme_url . '/style.css">' . __('View stylesheet') . '</a></p>';
                }

                $line .= '<div class="current-actions">';

                if (file_exists(Path::real($this->core->blog->themes_path . '/' . $id) . '/_config.php')) {
                    $line .= '<p><a href="' . $this->getURL('module=' . $id . '&amp;conf=1', false) . '" class="button submit">' . __('Configure theme') . '</a></p>';
                }

                # --BEHAVIOR-- adminCurrentThemeDetails
                $line .= $this->core->behaviors->call('adminCurrentThemeDetails', $id, $module);

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
        } elseif ((in_array('checkbox', $cols) || $count > 1) && !empty($actions) && $this->core->auth->isSuperAdmin()) {
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

        $this->core->blog->settings->addNamespace('system');
        if ($id != $this->core->blog->settings->system->theme) {

            # Select theme to use on curent blog
            if (in_array('select', $actions)) {
                $submits[] = '<input type="submit" name="select[' . Html::escapeHTML($id) . ']" value="' . __('Use this one') . '" />';
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
                    if ($this->core->auth->isSuperAdmin() && $module->writable() && empty($module->depMissing())) {
                        $submits[] = '<input type="submit" name="activate[' . Html::escapeHTML($id) . ']" value="' . __('Activate') . '" />';
                    }

                    break;

                # Activate
                case 'deactivate':
                    if ($this->core->auth->isSuperAdmin() && $module->writable() && empty($module->depChildren())) {
                        $submits[] = '<input type="submit" name="deactivate[' . Html::escapeHTML($id) . ']" value="' . __('Deactivate') . '" class="reset" />';
                    }

                    break;

                # Delete
                case 'delete':
                    if ($this->core->auth->isSuperAdmin() && $this->isDeletablePath($module->root()) && empty($module->depChildren())) {
                        $dev       = !preg_match('!^' . $this->path_pattern . '!', $module->root()) && defined('DOTCLEAR_MODE_DEV') && DOTCLEAR_MODE_DEV ? ' debug' : '';
                        $submits[] = '<input type="submit" class="delete ' . $dev . '" name="delete[' . Html::escapeHTML($id) . ']" value="' . __('Delete') . '" />';
                    }

                    break;

                # Clone
                case 'clone':
                    if ($this->core->auth->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" class="button clone" name="clone[' . Html::escapeHTML($id) . ']" value="' . __('Clone') . '" />';
                    }

                    break;

                # Install (from store)
                case 'install':
                    if ($this->core->auth->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="install[' . Html::escapeHTML($id) . ']" value="' . __('Install') . '" />';
                    }

                    break;

                # Update (from store)
                case 'update':
                    if ($this->core->auth->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update[' . Html::escapeHTML($id) . ']" value="' . __('Update') . '" />';
                    }

                    break;

                # Behavior
                case 'behavior':

                    # --BEHAVIOR-- adminModulesListGetActions
                    $tmp = $this->core->behaviors->call('adminModulesListGetActions', $this, $id, $module);

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

                    if ($this->core->auth->isSuperAdmin() && $this->path_writable) {
                        $submits[] = '<input type="submit" name="update" value="' . (
                            $with_selection ?
                            __('Update selected themes') :
                            __('Update all themes from this list')
                        ) . '" />' . $this->core->formNonce();
                    }

                    break;

                # Behavior
                case 'behavior':

                    # --BEHAVIOR-- adminModulesListGetGlobalActions
                    $tmp = $this->core->behaviors->call('adminModulesListGetGlobalActions', $this);

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

                if (!$this->modules->moduleExists($id)) {
                    throw new Exception(__('No such theme.'));
                }

                $this->core->blog->settings->addNamespace('system');
                $this->core->blog->settings->system->put('theme', $id);
                $this->core->blog->triggerBlog();

                $module = $this->modules->getModules($id);
                dcPage::addSuccessNotice(sprintf(__('Theme %s has been successfully selected.'), Html::escapeHTML($module['name'])));
                Http::redirect($this->getURL() . '#themes');
            }
        } else {
            if (!$this->isWritablePath()) {
                return;
            }

            if ($this->core->auth->isSuperAdmin() && !empty($_POST['activate'])) {
                if (is_array($_POST['activate'])) {
                    $modules = array_keys($_POST['activate']);
                }

                $list = $this->modules->getDisabledModules();
                if (empty($list)) {
                    throw new Exception(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    # --BEHAVIOR-- themeBeforeActivate
                    $this->core->callBehavior('themeBeforeActivate', $id);

                    $this->modules->activateModule($id);

                    # --BEHAVIOR-- themeAfterActivate
                    $this->core->callBehavior('themeAfterActivate', $id);

                    $count++;
                }

                dcPage::addSuccessNotice(
                    __('Theme has been successfully activated.', 'Themes have been successuflly activated.', $count)
                );
                Http::redirect($this->getURL());
            } elseif ($this->core->auth->isSuperAdmin() && !empty($_POST['deactivate'])) {
                if (is_array($_POST['deactivate'])) {
                    $modules = array_keys($_POST['deactivate']);
                }

                $list = $this->modules->getModules();
                if (empty($list)) {
                    throw new Exception(__('No such theme.'));
                }

                $failed = false;
                $count  = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    if (!$module['root_writable']) {
                        $failed = true;

                        continue;
                    }

                    $module[$id] = $id;

                    # --BEHAVIOR-- themeBeforeDeactivate
                    $this->core->callBehavior('themeBeforeDeactivate', $module);

                    $this->modules->deactivateModule($id);

                    # --BEHAVIOR-- themeAfterDeactivate
                    $this->core->callBehavior('themeAfterDeactivate', $module);

                    $count++;
                }

                if ($failed) {
                    dcPage::addWarningNotice(__('Some themes have not been deactivated.'));
                } else {
                    dcPage::addSuccessNotice(
                        __('Theme has been successfully deactivated.', 'Themes have been successuflly deactivated.', $count)
                    );
                }
                Http::redirect($this->getURL());
            } elseif ($this->core->auth->isSuperAdmin() && !empty($_POST['clone'])) {
                if (is_array($_POST['clone'])) {
                    $modules = array_keys($_POST['clone']);
                }

                $count = 0;
                foreach ($modules as $id) {
                    if (!$this->modules->moduleExists($id)) {
                        throw new Exception(__('No such theme.'));
                    }

                    # --BEHAVIOR-- themeBeforeClone
                    $this->core->callBehavior('themeBeforeClone', $id);

                    $this->modules->cloneModule($id);

                    # --BEHAVIOR-- themeAfterClone
                    $this->core->callBehavior('themeAfterClone', $id);

                    $count++;
                }

                dcPage::addSuccessNotice(
                    __('Theme has been successfully cloned.', 'Themes have been successuflly cloned.', $count)
                );
                Http::redirect($this->getURL());
            } elseif ($this->core->auth->isSuperAdmin() && !empty($_POST['delete'])) {
                if (is_array($_POST['delete'])) {
                    $modules = array_keys($_POST['delete']);
                }

                $list = $this->modules->getDisabledModules();

                $failed = false;
                $count  = 0;
                foreach ($modules as $id) {
                    if (!isset($list[$id])) {
                        if (!$this->modules->moduleExists($id)) {
                            throw new Exception(__('No such theme.'));
                        }

                        $module       = $this->modules->getModules($id);
                        $module['id'] = $id;

                        if (!$this->isDeletablePath($module['root'])) {
                            $failed = true;

                            continue;
                        }

                        # --BEHAVIOR-- themeBeforeDelete
                        $this->core->callBehavior('themeBeforeDelete', $module);

                        $this->modules->deleteModule($id);

                        # --BEHAVIOR-- themeAfterDelete
                        $this->core->callBehavior('themeAfterDelete', $module);
                    } else {
                        $this->modules->deleteModule($id, true);
                    }

                    $count++;
                }

                if (!$count && $failed) {
                    throw new Exception(__("You don't have permissions to delete this theme."));
                } elseif ($failed) {
                    dcPage::addWarningNotice(__('Some themes have not been delete.'));
                } else {
                    dcPage::addSuccessNotice(
                        __('Theme has been successfully deleted.', 'Themes have been successuflly deleted.', $count)
                    );
                }
                Http::redirect($this->getURL());
            } elseif ($this->core->auth->isSuperAdmin() && !empty($_POST['install'])) {
                if (is_array($_POST['install'])) {
                    $modules = array_keys($_POST['install']);
                }

                $list = $this->store->get();

                if (empty($list)) {
                    throw new Exception(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $id => $module) {
                    if (!in_array($id, $modules)) {
                        continue;
                    }

                    $dest = $this->getPath() . '/' . basename($module['file']);

                    # --BEHAVIOR-- themeBeforeAdd
                    $this->core->callBehavior('themeBeforeAdd', $module);

                    $this->store->process($module['file'], $dest);

                    # --BEHAVIOR-- themeAfterAdd
                    $this->core->callBehavior('themeAfterAdd', $module);

                    $count++;
                }

                dcPage::addSuccessNotice(
                    __('Theme has been successfully installed.', 'Themes have been successfully installed.', $count)
                );
                Http::redirect($this->getURL());
            } elseif ($this->core->auth->isSuperAdmin() && !empty($_POST['update'])) {
                if (is_array($_POST['update'])) {
                    $modules = array_keys($_POST['update']);
                }

                $list = $this->store->get(true);
                if (empty($list)) {
                    throw new Exception(__('No such theme.'));
                }

                $count = 0;
                foreach ($list as $module) {
                    if (!in_array($module['id'], $modules)) {
                        continue;
                    }

                    $dest = $module['root'] . '/../' . basename($module['file']);

                    # --BEHAVIOR-- themeBeforeUpdate
                    $this->core->callBehavior('themeBeforeUpdate', $module);

                    $this->store->process($module['file'], $dest);

                    # --BEHAVIOR-- themeAfterUpdate
                    $this->core->callBehavior('themeAfterUpdate', $module);

                    $count++;
                }

                $tab = $count && $count == count($list) ? '#themes' : '#update';

                dcPage::addSuccessNotice(
                    __('Theme has been successfully updated.', 'Themes have been successfully updated.', $count)
                );
                Http::redirect($this->getURL() . $tab);
            }

            # Manual actions
            elseif (!empty($_POST['upload_pkg']) && !empty($_FILES['pkg_file'])
                || !empty($_POST['fetch_pkg']) && !empty($_POST['pkg_url'])) {
                if (empty($_POST['your_pwd']) || !$this->core->auth->checkPassword($_POST['your_pwd'])) {
                    throw new Exception(__('Password verification failed'));
                }

                if (!empty($_POST['upload_pkg'])) {
                    Files::uploadStatus($_FILES['pkg_file']);

                    $dest = $this->getPath() . '/' . $_FILES['pkg_file']['name'];
                    if (!move_uploaded_file($_FILES['pkg_file']['tmp_name'], $dest)) {
                        throw new Exception(__('Unable to move uploaded file.'));
                    }
                } else {
                    $url  = urldecode($_POST['pkg_url']);
                    $dest = $this->getPath() . '/' . basename($url);
                    $this->store->download($url, $dest);
                }

                # --BEHAVIOR-- themeBeforeAdd
                $this->core->callBehavior('themeBeforeAdd', null);

                $ret_code = $this->store->install($dest);

                # --BEHAVIOR-- themeAfterAdd
                $this->core->callBehavior('themeAfterAdd', null);

                dcPage::addSuccessNotice(
                    $ret_code == 2 ?
                    __('The theme has been successfully updated.') :
                    __('The theme has been successfully installed.')
                );
                Http::redirect($this->getURL() . '#themes');
            } else {

                # --BEHAVIOR-- adminModulesListDoActions
                $this->core->callBehavior('adminModulesListDoActions', $this, $modules, 'theme');
            }
        }
    }

    //! todo: update this
    public function cloneModule($id)
    {
        $root = end($this->path); // Use last folder set in folders list (should be only one for theme)
        if (!is_dir($root) || !is_readable($root)) {
            throw new Exception(__('Themes folder unreachable'));
        }
        if (substr($root, -1) != '/') {
            $root .= '/';
        }
        if (($d = @dir($root)) === false) {
            throw new Exception(__('Themes folder unreadable'));
        }

        $counter = 0;
        $new_dir = sprintf('%s-copy', $this->modules[$id]['root']);
        while (is_dir($new_dir)) {
            $new_dir = sprintf('%s-copy-%s', $this->modules[$id]['root'], ++$counter);
        }
        $new_name = $this->modules[$id]['name'] . ($counter ? sprintf(__(' (copy #%s)'), $counter) : __(' (copy)'));

        if (!is_dir($new_dir)) {
            try {
                // Create destination folder named $new_dir in themes folder
                files::makeDir($new_dir, false);
                // Copy files
                $content = files::getDirList($this->modules[$id]['root']);
                foreach ($content['dirs'] as $dir) {
                    $rel = substr($dir, strlen($this->modules[$id]['root']));
                    if ($rel !== '') {
                        files::makeDir($new_dir . $rel);
                    }
                }
                foreach ($content['files'] as $file) {
                    $rel = substr($file, strlen($this->modules[$id]['root']));
                    copy($file, $new_dir . $rel);
                    if ($rel === '/_define.php') {
                        $buf = file_get_contents($new_dir . $rel);
                        // Find offset of registerModule function call
                        $pos = strpos($buf, '$this->registerModule');
                        // Change theme name to $new_name in _define.php
                        if (preg_match('/(\$this->registerModule\(\s*)((\s*|.*)+?)(\s*\);+)/m', $buf, $matches)) {
                            // Change only first occurence in registerModule parameters (should be the theme name)
                            $matches[2] = preg_replace('/' . preg_quote($this->modules[$id]['name']) . '/', $new_name, $matches[2], 1);
                            $buf        = substr($buf, 0, $pos) . $matches[1] . $matches[2] . $matches[4];
                            $buf .= sprintf("\n\n// Cloned on %s from %s theme.\n", date('c'), $this->modules[$id]['name']);
                            file_put_contents($new_dir . $rel, $buf);
                        } else {
                            throw new Exception(__('Unable to modify _config.php'));
                        }
                    }
                    if (substr($rel, -4) === '.php') {
                        // Change namespace in *.php
                        // ex: namespace themes\berlin; → namespace themes\berlinClone;
                        $buf = file_get_contents($new_dir . $rel);
                        if (preg_match('/^namespace\s*themes\\\([^;].*);$/m', $buf, $matches)) {
                            $pos     = strpos($buf, $matches[0]);
                            $rel_dir = substr($new_dir, strlen($root));
                            $ns      = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(['-', '.'], '', ucwords($rel_dir, '_-.')));
                            $buf     = substr($buf, 0, $pos) .
                                'namespace themes\\' . $ns . ';' .
                                substr($buf, $pos + strlen($matches[0]));
                            file_put_contents($new_dir . $rel, $buf);
                        }
                    }
                }
            } catch (Exception $e) {
                files::deltree($new_dir);

                throw new Exception($e->getMessage());
            }
        } else {
            throw new Exception(__('Destination folder already exist'));
        }
    }
}
