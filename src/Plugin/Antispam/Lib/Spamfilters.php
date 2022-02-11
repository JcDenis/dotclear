<?php
/**
 * @class Dotclear\Plugin\Antispam\Lib\Spamfilters
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginAntispam
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Antispam\Lib;

use Dotclear\Plugin\Antispam\Lib\Spamfilter;

use Dotclear\Database\Cursor;
use Dotclear\Database\Record;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Spamfilters
{
    private $filters     = [];
    private $filters_opt = [];

    public function init($filters): void
    {
        foreach ($filters as $class) {
            if (!is_subclass_of($class, 'Dotclear\\Plugin\\Antispam\\Lib\\Spamfilter')) {
                continue;
            }

            $f = new $class();
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

            $type    = $cur->comment_trackback ? 'trackback' : 'comment';
            $author  = $cur->comment_author;
            $email   = $cur->comment_email;
            $site    = $cur->comment_site;
            $ip      = $cur->comment_ip;
            $content = $cur->comment_content;
            $post_id = (int) $cur->post_id;
            $status  = null;

            $is_spam = $f->isSpam($type, $author, $email, $site, $ip, $content, $post_id, $status);

            if ($is_spam === true) {
                if ($f->auto_delete) {
                    $cur->clean();
                } else {
                    $cur->comment_status      = -2;
                    $cur->comment_spam_status = $status;
                    $cur->comment_spam_filter = $fid;
                }

                return true;
            } elseif ($is_spam === false) {
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

            $type    = $rs->comment_trackback ? 'trackback' : 'comment';
            $author  = $rs->comment_author;
            $email   = $rs->comment_email;
            $site    = $rs->comment_site;
            $ip      = $rs->comment_ip;
            $content = $rs->comment_content;

            $f->trainFilter($status, $filter_name, $type, $author, $email, $site, $ip, $content, $rs);
        }
    }

    public function statusMessage(Record $rs, string $filter_name): string
    {
        $f = $this->filters[$filter_name] ?? null;

        if ($f === null) {
            return __('Unknown filter.');
        }
        $status = $rs->spamStatus() ?: null;

        return $f->getStatusMessage($status, (int) $rs->comment_id);
    }

    public function saveFilterOpts(array $opts, bool $global = false): void
    {
        if ($global) {
            dotclear()->blog->settings->antispam->drop('antispam_filters');
        }
        dotclear()->blog->settings->antispam->put('antispam_filters', $opts, 'array', 'Antispam Filters', true, $global);
    }

    private function setFilterOpts(): void
    {
        if (dotclear()->blog->settings->antispam->antispam_filters !== null) {
            $this->filters_opt = dotclear()->blog->settings->antispam->antispam_filters;
        }

        # Create default options if needed
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

    private function orderCallBack(Spamfilter $a, Spamfilter $b)
    {
        if ($a->order == $b->order) {
            return 0;
        }

        return $a->order > $b->order ? 1 : -1;
    }
}
