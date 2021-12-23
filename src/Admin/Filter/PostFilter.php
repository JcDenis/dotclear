<?php
/**
 * @class Dotclear\Admin\Filter\PostFilter
 * @brief class for admin post list filters form
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Filter;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;
use Dotclear\Core\Utils;

use Dotclear\Admin\Filter;
use Dotclear\Admin\Filters;
use Dotclear\Admin\Combos;
use Dotclear\Admin\Filter\DefaultFilter;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}
class PostFilter extends Filter
{
    protected $post_type = 'post';

    public function __construct(Core $core, string $type = 'posts', string $post_type = '')
    {
        parent::__construct($core, $type);

        if (!empty($post_type) && array_key_exists($post_type, $core->getPostTypes())) {
            $this->post_type = $post_type;
            $this->add((new DefaultFilter('post_type', $post_type))->param('post_type'));
        }

        $filters = new \ArrayObject([
            Filters::getPageFilter(),
            $this->getPostUserFilter(),
            $this->getPostCategoriesFilter(),
            $this->getPostStatusFilter(),
            $this->getPostFormatFilter(),
            $this->getPostPasswordFilter(),
            $this->getPostSelectedFilter(),
            $this->getPostAttachmentFilter(),
            $this->getPostMonthFilter(),
            $this->getPostLangFilter(),
            $this->getPostCommentFilter(),
            $this->getPostTrackbackFilter()
        ]);

        # --BEHAVIOR-- adminPostFilter
        $core->callBehavior('adminPostFilter', $core, $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);
    }

    /**
     * Posts users select
     */
    public function getPostUserFilter(): ?DefaultFilter
    {
        $users = null;

        try {
            $users = $this->core->blog->getPostsUsers($this->post_type);
            if ($users->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());

            return null;
        }

        $combo = Combos::getUsersCombo($users);
        Utils::lexicalKeySort($combo);

        return (new DefaultFilter('user_id'))
            ->param()
            ->title(__('Author:'))
            ->options(array_merge(
                ['-' => ''],
                $combo
            ))
            ->prime(true);
    }

    /**
     * Posts categories select
     */
    public function getPostCategoriesFilter(): ?DefaultFilter
    {
        $categories = null;

        try {
            $categories = $this->core->blog->getCategories(['post_type' => $this->post_type]);
            if ($categories->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());

            return null;
        }

        $combo = [
            '-'            => '',
            __('(No cat)') => 'NULL'
        ];
        while ($categories->fetch()) {
            $combo[
                str_repeat('&nbsp;', ($categories->level - 1) * 4) .
                html::escapeHTML($categories->cat_title) . ' (' . $categories->nb_post . ')'
            ] = $categories->cat_id;
        }

        return (new DefaultFilter('cat_id'))
            ->param()
            ->title(__('Category:'))
            ->options($combo)
            ->prime(true);
    }

    /**
     * Posts status select
     */
    public function getPostStatusFilter(): DefaultFilter
    {
        return (new DefaultFilter('status'))
            ->param('post_status')
            ->title(__('Status:'))
            ->options(array_merge(
                ['-' => ''],
                Combos::getPostStatusesCombo()
            ))
            ->prime(true);
    }

    /**
     * Posts format select
     */
    public function getPostFormatFilter(): DefaultFilter
    {
        $core_formaters    = $this->core->getFormaters();
        $available_formats = [];
        foreach ($core_formaters as $editor => $formats) {
            foreach ($formats as $format) {
                $available_formats[$format] = $format;
            }
        }

        return (new DefaultFilter('format'))
            ->param('where', ['adminPostFilter', 'getPostFormatParam'])
            ->title(__('Format:'))
            ->options(array_merge(
                ['-' => ''],
                $available_formats
            ))
            ->prime(true);
    }

    public static function getPostFormatParam($f)
    {
        return " AND post_format = '" . $f[0] . "' ";
    }

    /**
     * Posts password state select
     */
    public function getPostPasswordFilter(): DefaultFilter
    {
        return (new DefaultFilter('password'))
            ->param('where', ['adminPostFilter', 'getPostPasswordParam'])
            ->title(__('Password:'))
            ->options([
                '-'                    => '',
                __('With password')    => '1',
                __('Without password') => '0'
            ])
            ->prime(true);
    }

    public static function getPostPasswordParam($f)
    {
        return ' AND post_password IS ' . ($f[0] ? 'NOT ' : '') . 'NULL ';
    }

    /**
     * Posts selected state select
     */
    public function getPostSelectedFilter(): DefaultFilter
    {
        return (new DefaultFilter('selected'))
            ->param('post_selected')
            ->title(__('Selected:'))
            ->options([
                '-'                => '',
                __('Selected')     => '1',
                __('Not selected') => '0'
            ]);
    }

    /**
     * Posts attachment state select
     */
    public function getPostAttachmentFilter(): DefaultFilter
    {
        return (new DefaultFilter('attachment'))
            ->param('media')
            ->param('link_type', 'attachment')
            ->title(__('Attachments:'))
            ->options([
                '-'                       => '',
                __('With attachments')    => '1',
                __('Without attachments') => '0'
            ]);
    }

    /**
     * Posts by month select
     */
    public function getPostMonthFilter(): ?DefaultFilter
    {
        $dates = null;

        try {
            $dates = $this->core->blog->getDates([
                'type'      => 'month',
                'post_type' => $this->post_type
            ]);
            if ($dates->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());

            return null;
        }

        return (new DefaultFilter('month'))
            ->param('post_month', ['adminPostFilter', 'getPostMonthParam'])
            ->param('post_year', ['adminPostFilter', 'getPostYearParam'])
            ->title(__('Month:'))
            ->options(array_merge(
                ['-' => ''],
                Combos::getDatesCombo($dates)
            ));
    }

    public static function getPostMonthParam($f)
    {
        return substr($f[0], 4, 2);
    }

    public static function getPostYearParam($f)
    {
        return substr($f[0], 0, 4);
    }

    /**
     * Posts lang select
     */
    public function getPostLangFilter(): ?DefaultFilter
    {
        $langs = null;

        try {
            $langs = $this->core->blog->getLangs(['post_type' => $this->post_type]);
            if ($langs->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());

            return null;
        }

        return (new DefaultFilter('lang'))
            ->param('post_lang')
            ->title(__('Lang:'))
            ->options(array_merge(
                ['-' => ''],
                Combos::getLangsCombo($langs, false)
            ));
    }

    /**
     * Posts comments state select
     */
    public function getPostCommentFilter(): DefaultFilter
    {
        return (new DefaultFilter('comment'))
            ->param('where', ['adminPostFilter', 'getPostCommentParam'])
            ->title(__('Comments:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0'
            ]);
    }

    public static function getPostCommentParam($f)
    {
        return " AND post_open_comment = '" . $f[0] . "' ";
    }

    /**
     * Posts trackbacks state select
     */
    public function getPostTrackbackFilter(): DefaultFilter
    {
        return (new DefaultFilter('trackback'))
            ->param('where', ['adminPostFilter', 'getPostYearParam'])
            ->title(__('Trackbacks:'))
            ->options([
                '-'          => '',
                __('Opened') => '1',
                __('Closed') => '0'
            ]);
    }

    public static function getPostTrackbackParam($f)
    {
        return " AND post_open_tb = '" . $f[0] . "' ";
    }
}
