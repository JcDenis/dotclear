<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common\Filter;

// Dotclear\Plugin\Antispam\Common\Filter\FilterWords
use Dotclear\App;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\Antispam\Common\Spamfilter;
use Exception;

/**
 * Antisâm Words filter.
 *
 * @ingroup  Plugin Antispam
 */
class FilterWords extends Spamfilter
{
    public $has_gui = true;
    public $name    = 'Bad Words';
    public $help    = 'words-filter';

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
            $word = $rs->field('rule_content');

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
        // Create list
        if (!GPC::post()->empty('createlist')) {
            try {
                $this->defaultWordsList();
                App::core()->notice()->addSuccessNotice(__('Words have been successfully added.'));
                Http::redirect($url);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Adding a word
        if (!GPC::post()->empty('swa')) {
            try {
                $this->addRule(
                    GPC::post()->string('swa'),
                    !GPC::post()->empty('globalsw') && App::core()->user()->isSuperAdmin()
                );
                App::core()->notice()->addSuccessNotice(__('Word has been successfully added.'));
                Http::redirect($url);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Removing spamwords
        if (!GPC::post()->empty('swd')) {
            try {
                $this->removeRule(GPC::post()->array('swd'));
                App::core()->notice()->addSuccessNotice(__('Words have been successfully removed.'));
                Http::redirect($url);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        /* DISPLAY
        ---------------------------------------------- */
        $res = '<form action="' . Html::escapeURL($url) . '" method="post" class="fieldset">' .
        '<p><label class="classic" for="swa">' . __('Add a word ') . '</label> ' . Form::field('swa', 20, 128);

        if (App::core()->user()->isSuperAdmin()) {
            $res .= '<label class="classic" for="globalsw">' . Form::checkbox('globalsw', 1) .
            __('Global word (used for all blogs)') . '</label> ';
        }

        $res .= App::core()->nonce()->form() .
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

                if (!$rs->field('blog_id')) {
                    $disabled_word = !App::core()->user()->isSuperAdmin();
                    $p_style .= ' global';
                }

                $item = '<p class="' . $p_style . '"><label class="classic" for="word-' . $rs->field('rule_id') . '">' .
                Form::checkbox(
                    ['swd[]', 'word-' . $rs->field('rule_id')],
                    $rs->field('rule_id'),
                    [
                        'disabled' => $disabled_word,
                    ]
                ) . ' ' .
                Html::escapeHTML($rs->field('rule_content')) .
                    '</label></p>';

                if ($rs->field('blog_id')) {
                    // local list
                    if ('' == $res_local) {
                        $res_local = '<h4>' . __('Local words (used only for this blog)') . '</h4>';
                    }
                    $res_local .= $item;
                } else {
                    // global list
                    if ('' == $res_global) {
                        $res_global = '<h4>' . __('Global words (used for all blogs)') . '</h4>';
                    }
                    $res_global .= $item;
                }
            }
            $res .= '<div class="local">' . $res_local . '</div>';
            $res .= '<div class="global">' . $res_global . '</div>';

            $res .= '</div>' .
            '<p>' . Form::hidden(['spamwords'], 1) .
            App::core()->nonce()->form() .
            '<input class="submit delete" type="submit" value="' . __('Delete selected words') . '"/></p>' .
                '</form>';
        }

        if (App::core()->user()->isSuperAdmin()) {
            $res .= '<form action="' . Html::escapeURL($url) . '" method="post">' .
            '<p><input type="submit" value="' . __('Create default wordlist') . '" />' .
            Form::hidden(['spamwords'], 1) .
            Form::hidden(['createlist'], 1) .
            App::core()->nonce()->form() . '</p>' .
                '</form>';
        }

        return $res;
    }

    private function getRules(string $type = 'all'): Record
    {
        $sql = new SelectStatement();
        $sql->columns([
            'rule_id',
            'blog_id',
            'rule_content',
        ]);
        $sql->where('rule_type = ' . $sql->quote('word'));
        $sql->and($sql->orGroup([
            'blog_id = ' . $sql->quote(App::core()->blog()->id),
            'blog_id IS NULL',
        ]));
        $sql->order([
            'blog_id ASC',
            'rule_content ASC',
        ]);
        $sql->from(App::core()->getPrefix() . 'spamrule');

        return $sql->select();
    }

    private function addRule(string $content, bool $general = false): void
    {
        $sql = new SelectStatement();
        $sql->from(App::core()->getPrefix() . 'spamrule');
        $sql->where('rule_type = ' . $sql->quote('word'));
        $sql->and('rule_content = ' . $sql->quote($content));

        if (!$general) {
            $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        }

        $record = $sql->select();

        if (!$record->isEmpty() && !$general) {
            throw new Exception(__('This word exists'));
        }

        if (!$record->isEmpty() && $general) {
            $sql = new UpdateStatement();
            $sql->set('rule_type = ' . $sql->quote('word'));
            $sql->set('rule_content = ' . $sql->quote($content));
            $sql->set(
                true === $general && App::core()->user()->isSuperAdmin() ?
                'blog_id = NULL' :
                'blog_id = ' . $sql->quote(App::core()->blog()->id)
            );
            $sql->where('rule_id = ' . $record->integer('rule_id'));
            $sql->from(App::core()->getPrefix() . 'spamrule');

            $sql->update();
        } else {
            $sql = new SelectStatement();
            $sql->column($sql->max('rule_id'));
            $sql->from(App::core()->getPrefix() . 'spamrule');

            $id = $sql->select()->integer() + 1;

            $sql = new InsertStatement();
            $sql->columns([
                'rule_type',
                'rule_content',
                'blog_id',
                'rule_id',
            ]);
            $sql->line([[
                $sql->quote('word'),
                $sql->quote($content),
                $general && App::core()->user()->isSuperAdmin() ? 'NULL' : $sql->quote(App::core()->blog()->id),
                $id,
            ]]);
            $sql->from(App::core()->getPrefix() . 'spamrule');

            $sql->insert();
        }
    }

    private function removeRule(int|array $ids): void
    {
        $sql = new DeleteStatement();

        if (is_array($ids)) {
            foreach ($ids as &$v) {
                $v = (int) $v;
            }
            $sql->where('rule_id' . $sql->in($ids));
        } else {
            $sql->where('rule_id = ' . $ids);
        }

        if (!App::core()->user()->isSuperAdmin()) {
            $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        }

        $sql->from(App::core()->getPrefix() . 'spamrule');

        $sql->delete();
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
            'zolus',
        ];

        foreach ($words as $w) {
            try {
                $this->addRule($w, true);
            } catch (\Exception) {
            }
        }
    }
}
