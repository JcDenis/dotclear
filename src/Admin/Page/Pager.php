<?php
/**
 * @class Dotclear\Admin\Page\Pager
 * @brief Dotclear admin menu helper
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Pager
{
    protected $env;
    protected $nb_elements;
    protected $nb_per_page;
    protected $nb_pages_per_group;

    protected $nb_pages;
    protected $nb_groups;
    protected $env_group;
    protected $index_group_start;
    protected $index_group_end;

    protected $page_url = null;

    public $index_start;
    public $index_end;

    public $base_url = null;

    public $var_page = 'page';

    public $html_cur_page = '<strong>%s</strong>';
    public $html_link_sep = '-';
    public $html_prev     = '&#171;prev.';
    public $html_next     = 'next&#187;';
    public $html_prev_grp = '...';
    public $html_next_grp = '...';

    protected $form_action;
    protected $form_hidden;

    /**
     * Constructor
     *
     * @param   int     $env                    Current page index
     * @param   int     $nb_elements            Total number of elements
     * @param   int     $nb_per_page            Number of items per page
     * @param   int     $nb_pages_per_group     Number of pages per group
     */
    public function __construct(int $env, int $nb_elements, int $nb_per_page = 10, int $nb_pages_per_group = 10)
    {
        $this->env                = abs((int) $env);
        $this->nb_elements        = abs((int) $nb_elements);
        $this->nb_per_page        = abs((int) $nb_per_page);
        $this->nb_pages_per_group = abs((int) $nb_pages_per_group);

        # Pages count
        $this->nb_pages = ceil($this->nb_elements / $this->nb_per_page);

        # Fix env value
        if ($this->env > $this->nb_pages || $this->env < 1) {
            $this->env = 1;
        }

        # Groups count
        $this->nb_groups = (int) ceil($this->nb_pages / $this->nb_pages_per_group);

        # Page first element index
        $this->index_start = ($this->env - 1) * $this->nb_per_page;

        # Page last element index
        $this->index_end = $this->index_start + $this->nb_per_page - 1;
        if ($this->index_end >= $this->nb_elements) {
            $this->index_end = $this->nb_elements - 1;
        }

        # Current group
        $this->env_group = (int) ceil($this->env / $this->nb_pages_per_group);

        # Group first page index
        $this->index_group_start = ($this->env_group - 1) * $this->nb_pages_per_group + 1;

        # Group last page index
        $this->index_group_end = $this->index_group_start + $this->nb_pages_per_group - 1;
        if ($this->index_group_end > $this->nb_pages) {
            $this->index_group_end = $this->nb_pages;
        }
    }

    /**
     * Get the link
     *
     * @param   string  $li_class           The li class
     * @param   string  $href               The href
     * @param   string  $img_src            The image source
     * @param   string  $img_src_nolink     The image source nolink
     * @param   string  $img_alt            The image alternate
     * @param   bool    $enable_link        The enable link
     *
     * @return  string                      The link
     */
    protected function getLink(string $li_class, string $href, string $img_src, string $img_src_nolink, string $img_alt, bool $enable_link): string
    {
        if ($enable_link) {
            $formatter = '<li class="%s btn"><a href="%s"><img src="?df=%s" alt="%s"/></a><span class="hidden">%s</span></li>';

            return sprintf($formatter, $li_class, $href, $img_src, $img_alt, $img_alt);
        }
        $formatter = '<li class="%s no-link btn"><img src="?df=%s" alt="%s"/></li>';

        return sprintf($formatter, $li_class, $img_src_nolink, $img_alt);
    }

    /**
     * Set the url.
     */
    public function setURL(): void
    {
        if ($this->base_url !== null) {
            $this->page_url = $this->base_url;

            return;
        }

        $url = (string) $_SERVER['REQUEST_URI'];

        # Removing session information
        if (session_id()) {
            $url = preg_replace('/' . preg_quote(session_name() . '=' . session_id(), '/') . '([&]?)/', '', $url);
            $url = preg_replace('/&$/', '', $url);
        }

        # Escape page_url for sprintf
        $url = preg_replace('/%/', '%%', $url);

        # Changing page ref
        if (preg_match('/[?&]' . $this->var_page . '=[0-9]+/', $url)) {
            $url = preg_replace('/([?&]' . $this->var_page . '=)[0-9]+/', '$1%1$d', $url);
        } elseif (preg_match('/[?]/', $url)) {
            $url .= '&' . $this->var_page . '=%1$d';
        } else {
            $url .= '?' . $this->var_page . '=%1$d';
        }

        $this->page_url = Html::escapeHTML($url);

        $url = parse_url($_SERVER['REQUEST_URI']);
        if (isset($url['query'])) {
            parse_str($url['query'], $args);
        } else {
            $args = [];
        }
        # Removing session information
        if (session_id()) {
            if (isset($args[session_name()])) {
                unset($args[session_name()]);
            }
        }
        if (isset($args[$this->var_page])) {
            unset($args[$this->var_page]);
        }
        if (isset($args['ok'])) {
            unset($args['ok']);
        }

        $this->form_hidden = '';
        foreach ($args as $k => $v) {
            // Check parameter key (will prevent some forms of XSS)
            if ($k === preg_replace('`[^A-Za-z0-9_-]`', '', $k)) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        $this->form_hidden .= Form::hidden([$k . '[]'], Html::escapeHTML($v2));
                    }
                } else {
                    $this->form_hidden .= Form::hidden([$k], Html::escapeHTML($v));
                }
            }
        }
        $this->form_action = $url['path'];
    }

    /**
     * Pager Links
     *
     * @return  string  The pager link
     */
    public function getLinks(): string
    {
        $this->setURL();
        $htmlFirst = $this->getLink(
            'first',
            sprintf($this->page_url, 1),
            'images/pagination/first.svg',
            'images/pagination/no-first.svg',
            __('First page'),
            ($this->env > 1)
        );
        $htmlPrev = $this->getLink(
            'prev',
            sprintf($this->page_url, $this->env - 1),
            'images/pagination/previous.svg',
            'images/pagination/no-previous.svg',
            __('Previous page'),
            ($this->env > 1)
        );
        $htmlNext = $this->getLink(
            'next',
            sprintf($this->page_url, $this->env + 1),
            'images/pagination/next.svg',
            'images/pagination/no-next.svg',
            __('Next page'),
            ($this->env < $this->nb_pages)
        );
        $htmlLast = $this->getLink(
            'last',
            sprintf($this->page_url, $this->nb_pages),
            'images/pagination/last.svg',
            'images/pagination/no-last.svg',
            __('Last page'),
            ($this->env < $this->nb_pages)
        );
        $htmlCurrent = '<li class="active"><strong>' .
        sprintf(__('Page %s / %s'), $this->env, $this->nb_pages) .
            '</strong></li>';

        $htmlDirect = ($this->nb_pages > 1 ?
            sprintf(
                '<li class="direct-access">' . __('Direct access page %s'),
                Form::number([$this->var_page], 1, (int) $this->nb_pages)
            ) .
            '<input type="submit" value="' . __('ok') . '" class="reset" ' .
            'name="ok" />' . $this->form_hidden . '</li>' : '');

        $res = '<form action="' . $this->form_action . '" method="get">' .
            '<div class="pager"><ul>' .
            $htmlFirst .
            $htmlPrev .
            $htmlCurrent .
            $htmlNext .
            $htmlLast .
            $htmlDirect .
            '</ul>' .
            '</div>' .
            '</form>'
        ;

        return $this->nb_elements > 0 ? $res : '';
    }

    public function debug(): string
    {
        return
        'Elements per page ........... ' . $this->nb_per_page . "\n" .
        'Pages per group.............. ' . $this->nb_pages_per_group . "\n" .
        'Elements count .............. ' . $this->nb_elements . "\n" .
        'Pages ....................... ' . $this->nb_pages . "\n" .
        'Groups ...................... ' . $this->nb_groups . "\n\n" .
        'Current page .................' . $this->env . "\n" .
        'Start index ................. ' . $this->index_start . "\n" .
        'End index ................... ' . $this->index_end . "\n" .
        'Current group ............... ' . $this->env_group . "\n" .
        'Group first page index ...... ' . $this->index_group_start . "\n" .
        'Group last page index ....... ' . $this->index_group_end;
    }
}
