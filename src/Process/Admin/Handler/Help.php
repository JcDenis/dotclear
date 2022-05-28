<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Help
use Dotclear\App;
use Dotclear\Process\Admin\Page\AbstractPage;
use Dotclear\Helper\GPC\GPC;

/**
 * Admin help page.
 *
 * @ingroup  Admin Help Localisation Handler
 */
class Help extends AbstractPage
{
    private $help_title   = '';
    private $help_content = '';

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getPagePrepend(): ?bool
    {
        $help_page = GPC::get()->string('page', 'index');

        $this->getHelpContent($help_page);
        if ('' == $this->help_content || 'index' == $help_page) {
            $this->getHelpContent('index');
        }

        if ('' != $this->help_title) {
            $this->setPageBreadcrumb([
                __('Global help') => App::core()->adminurl()->get('admin.help'),
                $this->help_title => '',
            ]);
        } else {
            $this->setPageBreadcrumb([__('Global help') => '']);
        }

        $this
            ->setPageTitle(__('Global help'))
            ->setPageHead(App::core()->resource()->pageTabs('first-step'))
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        echo $this->help_content;

        // Prevents global help link display
        App::core()->help()->flag(true);
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

            if (!($f = App::core()->help()->context($v))) {
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
