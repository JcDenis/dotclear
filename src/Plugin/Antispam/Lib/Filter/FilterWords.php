<?php
/**
 * @class Dotclear\Plugin\Antispam\Lib\Filter\FilterWords
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Lib\Filter;


use Dotclear\Plugin\Antispam\Lib\Spamfilter;

use Dotclear\Html\Html;
Use Dotclear\Html\Form;
use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class FilterWords extends Spamfilter
{
    public $has_gui = true;
    public $name    = 'Bad Words';
    public $help    = 'words-filter';

    private $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = dotclear()->prefix . 'spamrule';
    }

    protected function setInfo(): void
    {
        $this->description = __('Words Blocklist');
    }

    public function getStatusMessage(string $status, int $comment_id): string
    {
        return sprintf(__('Filtered by %1$s with word %2$s.'), $this->guiLink(), '<em>' . $status . '</em>');
    }

    public function isSpam(string $type, string $author, string $email, string $site, string $ip, string $content, int $post_id, ?int &$status): ?bool
    {
        $str = $author . ' ' . $email . ' ' . $site . ' ' . $content;

        $rs = $this->getRules();

        while ($rs->fetch()) {
            $word = $rs->rule_content;

            if (substr($word, 0, 1) == '/' && substr($word, -1, 1) == '/') {
                $reg = substr(substr($word, 1), 0, -1);
            } else {
                $reg = preg_quote($word, '/');
                $reg = '(^|\s+|>|<)' . $reg . '(>|<|\s+|\.|$)';
            }

            if (preg_match('/' . $reg . '/msiu', $str)) {
                $status = $word;

                return true;
            }
        }

        return null;
    }

    public function gui(string $url): string
    {
        # Create list
        if (!empty($_POST['createlist'])) {
            try {
                $this->defaultWordsList();
                dotclear()->notices->addSuccessNotice(__('Words have been successfully added.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Adding a word
        if (!empty($_POST['swa'])) {
            $globalsw = !empty($_POST['globalsw']) && dotclear()->auth->isSuperAdmin();

            try {
                $this->addRule($_POST['swa'], $globalsw);
                dotclear()->notices->addSuccessNotice(__('Word has been successfully added.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Removing spamwords
        if (!empty($_POST['swd']) && is_array($_POST['swd'])) {
            try {
                $this->removeRule($_POST['swd']);
                dotclear()->notices->addSuccessNotice(__('Words have been successfully removed.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        $res = '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<p><label class="classic" for="swa">' . __('Add a word ') . '</label> ' . Form::field('swa', 20, 128);

        if (dotclear()->auth->isSuperAdmin()) {
            $res .= '<label class="classic" for="globalsw">' . Form::checkbox('globalsw', 1) .
            __('Global word (used for all blogs)') . '</label> ';
        }

        $res .= dotclear()->formNonce() .
        '</p>' .
        '<p><input type="submit" value="' . __('Add') . '"/></p>' .
            '</form>';

        $rs = $this->getRules();
        if ($rs->isEmpty()) {
            $res .= '<p><strong>' . __('No word in list.') . '</strong></p>';
        } else {
            $res .= '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
            '<h3>' . __('List of bad words') . '</h3>' .
                '<div class="antispam">';

            $res_global = '';
            $res_local  = '';
            while ($rs->fetch()) {
                $disabled_word = false;

                $p_style = '';

                if (!$rs->blog_id) {
                    $disabled_word = !dotclear()->auth->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="word-' . $rs->rule_id . '">' .
                Form::checkbox(['swd[]', 'word-' . $rs->rule_id], $rs->rule_id,
                    [
                        'disabled' => $disabled_word
                    ]
                ) . ' ' .
                Html::escapeHTML($rs->rule_content) .
                    '</label></p>';

                if ($rs->blog_id) {
                    // local list
                    if ($res_local == '') {
                        $res_local = '<h4>' . __('Local words (used only for this blog)') . '</h4>';
                    }
                    $res_local .= $item;
                } else {
                    // global list
                    if ($res_global == '') {
                        $res_global = '<h4>' . __('Global words (used for all blogs)') . '</h4>';
                    }
                    $res_global .= $item;
                }
            }
            $res .= '<div class="local">' . $res_local . '</div>';
            $res .= '<div class="global">' . $res_global . '</div>';

            $res .= '</div>' .
            '<p>' . Form::hidden(['spamwords'], 1) .
            dotclear()->formNonce() .
            '<input class="submit delete" type="submit" value="' . __('Delete selected words') . '"/></p>' .
                '</form>';
        }

        if (dotclear()->auth->isSuperAdmin()) {
            $res .= '<form action="' . Html::escapeURL($url) . '" method="post">' .
            '<p><input type="submit" value="' . __('Create default wordlist') . '" />' .
            Form::hidden(['spamwords'], 1) .
            Form::hidden(['createlist'], 1) .
            dotclear()->formNonce() . '</p>' .
                '</form>';
        }

        return $res;
    }

    private function getRules()
    {
        $strReq = 'SELECT rule_id, blog_id, rule_content ' .
        'FROM ' . $this->table . ' ' .
        "WHERE rule_type = 'word' " .
        "AND ( blog_id = '" . dotclear()->con->escape(dotclear()->blog->id) . "' " .
            'OR blog_id IS NULL ) ' .
            'ORDER BY blog_id ASC, rule_content ASC ';

        return dotclear()->con->select($strReq);
    }

    private function addRule($content, $general = false)
    {
        $strReq = 'SELECT rule_id FROM ' . $this->table . ' ' .
        "WHERE rule_type = 'word' " .
        "AND rule_content = '" . dotclear()->con->escape($content) . "' ";
        if (!$general) {
            $strReq .= ' AND blog_id = \'' . dotclear()->blog->id . '\'';
        }
        $rs = dotclear()->con->select($strReq);

        if (!$rs->isEmpty() && !$general) {
            throw new \Exception(__('This word exists'));
        }

        $cur               = dotclear()->con->openCursor($this->table);
        $cur->rule_type    = 'word';
        $cur->rule_content = (string) $content;

        if ($general && dotclear()->auth->isSuperAdmin()) {
            $cur->blog_id = null;
        } else {
            $cur->blog_id = dotclear()->blog->id;
        }

        if (!$rs->isEmpty() && $general) {
            $cur->update('WHERE rule_id = ' . $rs->rule_id);
        } else {
            $rs_max       = dotclear()->con->select('SELECT MAX(rule_id) FROM ' . $this->table);
            $cur->rule_id = (integer) $rs_max->f(0) + 1;
            $cur->insert();
        }
    }

    private function removeRule($ids)
    {
        $strReq = 'DELETE FROM ' . $this->table . ' ';

        if (is_array($ids)) {
            foreach ($ids as &$v) {
                $v = (integer) $v;
            }
            $strReq .= 'WHERE rule_id IN (' . implode(',', $ids) . ') ';
        } else {
            $ids = (integer) $ids;
            $strReq .= 'WHERE rule_id = ' . $ids . ' ';
        }

        if (!dotclear()->auth->isSuperAdmin()) {
            $strReq .= "AND blog_id = '" . dotclear()->con->escape(dotclear()->blog->id) . "' ";
        }

        dotclear()->con->execute($strReq);
    }

    public function defaultWordsList()
    {
        $words = [
            '/-credit(\s+|$)/',
            '/-digest(\s+|$)/',
            '/-loan(\s+|$)/',
            '/-online(\s+|$)/',
            '4u',
            'adipex',
            'advicer',
            'ambien',
            'baccarat',
            'baccarrat',
            'blackjack',
            'bllogspot',
            'bolobomb',
            'booker',
            'byob',
            'car-rental-e-site',
            'car-rentals-e-site',
            'carisoprodol',
            'cash',
            'casino',
            'casinos',
            'chatroom',
            'cialis',
            'craps',
            'credit-card',
            'credit-report-4u',
            'cwas',
            'cyclen',
            'cyclobenzaprine',
            'dating-e-site',
            'day-trading',
            'debt',
            'digest-',
            'discount',
            'discreetordering',
            'duty-free',
            'dutyfree',
            'estate',
            'favourits',
            'fioricet',
            'flowers-leading-site',
            'freenet',
            'freenet-shopping',
            'gambling',
            'gamias',
            'health-insurancedeals-4u',
            'holdem',
            'holdempoker',
            'holdemsoftware',
            'holdemtexasturbowilson',
            'hotel-dealse-site',
            'hotele-site',
            'hotelse-site',
            'incest',
            'insurance-quotesdeals-4u',
            'insurancedeals-4u',
            'jrcreations',
            'levitra',
            'macinstruct',
            'mortgage',
            'online-gambling',
            'onlinegambling-4u',
            'ottawavalleyag',
            'ownsthis',
            'palm-texas-holdem-game',
            'paxil',
            'pharmacy',
            'phentermine',
            'pills',
            'poker',
            'poker-chip',
            'poze',
            'prescription',
            'rarehomes',
            'refund',
            'rental-car-e-site',
            'roulette',
            'shemale',
            'slot',
            'slot-machine',
            'soma',
            'taboo',
            'tamiflu',
            'texas-holdem',
            'thorcarlson',
            'top-e-site',
            'top-site',
            'tramadol',
            'trim-spa',
            'ultram',
            'v1h',
            'vacuum',
            'valeofglamorganconservatives',
            'viagra',
            'vicodin',
            'vioxx',
            'xanax',
            'zolus'
        ];

        foreach ($words as $w) {
            try {
                $this->addRule($w, true);
            } catch (\Exception $e) {
            }
        }
    }
}
