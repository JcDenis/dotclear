<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Common;

// Dotclear\Plugin\Antispam\Common\Spamfilters
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;

/**
 * Antispam filters stack.
 *
 * @ingroup  Plugin Antispam Stack
 */
class Spamfilters
{
    private $filters     = [];
    private $filters_opt = [];

    public function init($filters): void
    {
        foreach ($filters as $class) {
            if (!is_subclass_of($class, __NAMESPACE__ . '\\Spamfilter')) {
                continue;
            }

            $f                     = new $class();
            $this->filters[$f->id] = $f;
        }

        $this->setFilterOpts();
        if (!empty($this->filters_opt)) {
            uasort($this->filters, [$this, 'orderCallBack']);
        }
    }

    public function getFilter($f): ?Spamfilter
    {
        return $this->filters[$f] ?: null;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function isSpam(Cursor $cur): bool
    {
        foreach ($this->filters as $fid => $f) {
            if (!$f->active) {
                continue;
            }

            $type    = $cur->getField('comment_trackback') ? 'trackback' : 'comment';
            $author  = $cur->getField('comment_author');
            $email   = $cur->getField('comment_email');
            $site    = $cur->getField('comment_site');
            $ip      = $cur->getField('comment_ip');
            $content = $cur->getField('comment_content');
            $post_id = (int) $cur->getField('post_id');
            $status  = null;

            $is_spam = $f->isSpam($type, $author, $email, $site, $ip, $content, $post_id, $status);

            if (true === $is_spam) {
                if ($f->auto_delete) {
                    $cur->clean();
                } else {
                    $cur->setField('comment_status', -2);
                    $cur->setField('comment_spam_status', $status);
                    $cur->setField('comment_spam_filter', $fid);
                }

                return true;
            }
            if (false === $is_spam) {
                return false;
            }
        }

        return false;
    }

    public function trainFilters(Record $rs, string $status, string $filter_name): void
    {
        foreach ($this->filters as $fid => $f) {
            if (!$f->active) {
                continue;
            }

            $type    = $rs->f('comment_trackback') ? 'trackback' : 'comment';
            $author  = $rs->f('comment_author');
            $email   = $rs->f('comment_email');
            $site    = $rs->f('comment_site');
            $ip      = $rs->f('comment_ip');
            $content = $rs->f('comment_content');

            $f->trainFilter($status, $filter_name, $type, $author, $email, $site, $ip, $content, $rs);
        }
    }

    public function statusMessage(Record $rs, string $filter_name): string
    {
        $f = $this->filters[$filter_name] ?? null;

        if (null === $f) {
            return __('Unknown filter.');
        }
        $status = $rs->call('spamStatus') ?: null;

        return $f->getStatusMessage($status, $rs->fInt('comment_id'));
    }

    public function saveFilterOpts(array $opts, bool $global = false): void
    {
        if (true === $global) {
            App::core()->blog()->settings()->getGroup('antispam')->dropSetting('antispam_filters');
        }
        App::core()->blog()->settings()->getGroup('antispam')->putSetting('antispam_filters', $opts, 'array', 'Antispam Filters', true, $global);
    }

    private function setFilterOpts(): void
    {
        if (null !== App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_filters')) {
            $this->filters_opt = App::core()->blog()->settings()->getGroup('antispam')->getSetting('antispam_filters');
        }

        // Create default options if needed
        if (!is_array($this->filters_opt)) {
            $this->saveFilterOpts([], true);
            $this->filters_opt = [];
        }

        foreach ($this->filters_opt as $k => $o) {
            if (isset($this->filters[$k]) && is_array($o)) {
                $this->filters[$k]->active      = $o[0] ?? false;
                $this->filters[$k]->order       = $o[1] ?? 0;
                $this->filters[$k]->auto_delete = $o[2] ?? false;
            }
        }
    }

    private function orderCallBack(Spamfilter $a, Spamfilter $b): int
    {
        if ($a->order == $b->order) {
            return 0;
        }

        return $a->order > $b->order ? 1 : -1;
    }
}
