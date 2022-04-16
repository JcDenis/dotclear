<?php
/**
 * @class Dotclear\Plugin\Antispam\Common\Filter\FilterWords
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common\Filter;

use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\Html\Html;
Use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Antispam\Common\Spamfilter;

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
            $word = $rs->f('rule_content');

            if ('/' == substr($word, 0, 1) && '/' == substr($word, -1, 1)) {
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
                dotclear()->notice()->addSuccessNotice(__('Words have been successfully added.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Adding a word
        if (!empty($_POST['swa'])) {
            $globalsw = !empty($_POST['globalsw']) && dotclear()->user()->isSuperAdmin();

            try {
                $this->addRule($_POST['swa'], $globalsw);
                dotclear()->notice()->addSuccessNotice(__('Word has been successfully added.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Removing spamwords
        if (!empty($_POST['swd']) && is_array($_POST['swd'])) {
            try {
                $this->removeRule($_POST['swd']);
                dotclear()->notice()->addSuccessNotice(__('Words have been successfully removed.'));
                Http::redirect($url);
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        $res = '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<p><label class="classic" for="swa">' . __('Add a word ') . '</label> ' . Form::field('swa', 20, 128);

        if (dotclear()->user()->isSuperAdmin()) {
            $res .= '<label class="classic" for="globalsw">' . Form::checkbox('globalsw', 1) .
            __('Global word (used for all blogs)') . '</label> ';
        }

        $res .= dotclear()->nonce()->form() .
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

                if (!$rs->f('blog_id')) {
                    $disabled_word = !dotclear()->user()->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="word-' . $rs->f('rule_id') . '">' .
                Form::checkbox(['swd[]', 'word-' . $rs->f('rule_id')], $rs->f('rule_id'),
                    [
                        'disabled' => $disabled_word
                    ]
                ) . ' ' .
                Html::escapeHTML($rs->f('rule_content')) .
                    '</label></p>';

                if ($rs->f('blog_id')) {
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
            dotclear()->nonce()->form() .
            '<input class="submit delete" type="submit" value="' . __('Delete selected words') . '"/></p>' .
                '</form>';
        }

        if (dotclear()->user()->isSuperAdmin()) {
            $res .= '<form action="' . Html::escapeURL($url) . '" method="post">' .
            '<p><input type="submit" value="' . __('Create default wordlist') . '" />' .
            Form::hidden(['spamwords'], 1) .
            Form::hidden(['createlist'], 1) .
            dotclear()->nonce()->form() . '</p>' .
                '</form>';
        }

        return $res;
    }

    private function getRules(string $type = 'all'): Record
    {
        $sql = new SelectStatement(__METHOD__);

        return $sql
            ->columns([
                'rule_id',
                'blog_id',
                'rule_content',
            ])
            ->where('rule_type = ' . $sql->quote('word'))
            ->and($sql->orGroup([
                'blog_id = ' . $sql->quote(dotclear()->blog()->id),
                'blog_id IS NULL',
            ]))
            ->order([
                'blog_id ASC',
                'rule_content ASC'
            ])
            ->from($this->table)
            ->select();
    }

    private function addRule(string $content, bool $general = false): void
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->from($this->table)
            ->where('rule_type = ' . $sql->quote('word'))
            ->and('rule_content = ' . $sql->quote($content));

        if (!$general) {
            $sql->and('blog_id = ' . $sql->quote(dotclear()->blog()->id));
        }

        $rs = $sql->select();

        if (!$rs->isEmpty() && !$general) {
            throw new \Exception(__('This word exists'));
        }

        if (!$rs->isEmpty() && $general) {
            $sql = new UpdateStatement(__METHOD__);
            $sql
                ->set('rule_type = ' . $sql->quote('word'))
                ->set('rule_content = ' . $sql->quote($content))
                ->set(true === $general && dotclear()->user()->isSuperAdmin() ?
                    'blog_id = NULL' :
                    'blog_id = ' . $sql->quote(dotclear()->blog()->id)
                )
                ->where('rule_id = ' . $rs->fInt('rule_id'))
                ->update();
        } else {
            $sql = new InsertStatement(__METHOD__);
            $sql
                ->columns([
                    'rule_type',
                    'rule_content',
                    'blog_id',
                    'rule_id',
                ])
                ->line([[
                    $sql->quote('word'),
                    $sql->quote($content),
                    $general && dotclear()->user()->isSuperAdmin() ? 'NULL' : $sql->quote(dotclear()->blog()->id),
                    SelectStatement::init(__METHOD__)
                        ->column($sql->max('rule_id'))
                        ->from($this->table)
                        ->select()
                        ->fInt() + 1,
                ]])
                ->insert();
        }
    }

    private function removeRule(int|array $ids): void
    {
        $sql = new DeleteStatement(__METHOD__);

        if (is_array($ids)) {
            foreach ($ids as &$v) {
                $v = (int) $v;
            }
            $sql->where('rule_id' . $sql->in($ids));
        } else {
            $sql->where('rule_id = ' . $ids);
        }

        if (!dotclear()->user()->isSuperAdmin()) {
            $sql->and('blog_id = ' . $sql->quote(dotclear()->blog()->id));
        }

        $sql
            ->from($this->table)
            ->delete();
    }

    public function defaultWordsList(): void
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
            } catch (\Exception) {
            }
        }
    }
}
