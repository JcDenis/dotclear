<?php
/**
 * @note Dotclear\Process\Admin\Handler\Help
 * @brief Dotclear admin help page
 *
 * @ingroup  Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Helper\Html\Html;

class Help extends AbstractPage
{
    private $help_title   = '';
    private $help_content = '';

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $help_page = !empty($_GET['page']) ? Html::escapeHTML($_GET['page']) : 'index';

        $this->getHelpContent($help_page);
        if (('' == $this->help_content) || ('index' == $help_page)) {
            $this->getHelpContent('index');
        }

        if ('' != $this->help_title) {
            $this->setPageBreadcrumb([
                __('Global help') => dotclear()->adminurl()->get('admin.help'),
                $this->help_title => '',
            ]);
        } else {
            $this->setPageBreadcrumb([__('Global help') => '']);
        }

        $this
            ->setPageTitle(__('Global help'))
            ->setPageHead(dotclear()->resource()->pageTabs('first-step'))
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo $this->help_content;

        // Prevents global help link display
        dotclear()->help()->flag(true);
    }

    private function getHelpContent(...$args): void
    {
        if (empty($args)) {
            return;
        }

        foreach ($args as $v) {
            if (is_object($v) && isset($v->content)) {
                $this->help_content .= $v->content;

                continue;
            }

            if (!($f = dotclear()->help()->context($v))) {
                continue;
            }
            if (!file_exists($f) || !is_readable($f)) {
                continue;
            }

            $fc = file_get_contents($f);
            if (preg_match('|<body[^>]*?>(.*?)</body>|ms', $fc, $matches)) {
                $this->help_content .= $matches[1];
                if (preg_match('|<title[^>]*?>(.*?)</title>|ms', $fc, $matches)) {
                    $this->help_title = $matches[1];
                }
            } else {
                $this->help_content .= $fc;
            }
        }

        if (trim($this->help_content) == '') {
            $this->help_content = $this->help_title = '';

            return;
        }
    }
}
