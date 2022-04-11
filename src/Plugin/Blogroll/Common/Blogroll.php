<?php
/**
 * @class Dotclear\Plugin\Blogroll\Common\Blogroll
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Common;

use ArrayObject;

use Dotclear\Database\Record;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\ModuleException;

class Blogroll
{
    private $table = 'link';

    public function getLinks(array|ArrayObject $params = []): Record
    {
        $strReq = 'SELECT link_id, link_title, link_desc, link_href, ' .
        'link_lang, link_xfn, link_position ' .
        'FROM ' . dotclear()->prefix . $this->table . ' ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' ";

        if (isset($params['link_id'])) {
            $strReq .= 'AND link_id = ' . (int) $params['link_id'] . ' ';
        }

        $strReq .= 'ORDER BY link_position ';

        $rs = dotclear()->con()->select($strReq);
        $rs = $rs->toStatic();

        $this->setLinksData($rs);

        return $rs;
    }

    public function getLangs(array|ArrayObject $params = []): Record
    {
        # Use post_lang as an alias of link_lang to be able to use the dcAdminCombos::getLangsCombo() function
        $strReq = 'SELECT COUNT(link_id) as nb_link, link_lang as post_lang ' .
        'FROM ' . dotclear()->prefix . $this->table . ' ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            "AND link_lang <> '' " .
            'AND link_lang IS NOT NULL ';

        if (isset($params['lang'])) {
            $strReq .= "AND link_lang = '" .dotclear()->con()->escape($params['lang']) . "' ";
        }

        $strReq .= 'GROUP BY link_lang ';

        $order = 'desc';
        if (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order'])) {
            $order = $params['order'];
        }
        $strReq .= 'ORDER BY link_lang ' . $order . ' ';

        return dotclear()->con()->select($strReq);
    }

    public function getLink(int $id): Record
    {
        return $this->getLinks(['link_id' => $id]);
    }

    public function addLink(string $title, string $href, string $desc = '', string $lang = '', string $xfn = ''): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('blog_id', dotclear()->blog()->id);
        $cur->setField('link_title', $title);
        $cur->setField('link_href', $href);
        $cur->setField('link_desc', $desc);
        $cur->setField('link_lang', $lang);
        $cur->setField('link_xfn', $xfn);

        if ('' == $cur->getField('link_title')) {
            throw new ModuleException(__('You must provide a link title'));
        }

        if ('' == $cur->getField('link_href')) {
            throw new ModuleException(__('You must provide a link URL'));
        }

        $strReq       = 'SELECT MAX(link_id) FROM ' . dotclear()->prefix . $this->table;
        $cur->setField('link_id', dotclear()->con()->select($strReq)->fInt() + 1);

        $cur->insert();
        dotclear()->blog()->triggerBlog();
    }

    public function updateLink(int $id, string $title, string $href, string $desc = '', string $lang = '', string $xfn = ''): void
    {
        $cur =dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('link_title', $title);
        $cur->setField('link_href', $href);
        $cur->setField('link_desc', $desc);
        $cur->setField('link_lang', $lang);
        $cur->setField('link_xfn', $xfn);

        if ('' == $cur->getField('link_title')) {
            throw new ModuleException(__('You must provide a link title'));
        }

        if ('' == $cur->getField('link_href')) {
            throw new ModuleException(__('You must provide a link URL'));
        }

        $cur->update('WHERE link_id = ' . $id .
            " AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "'");
        dotclear()->blog()->triggerBlog();
    }

    public function updateCategory(int $id, string $desc): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('link_desc', $desc);

        if ('' == $cur->getField('link_desc')) {
            throw new ModuleException(__('You must provide a category title'));
        }

        $cur->update('WHERE link_id = ' . $id .
            " AND blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "'");
        dotclear()->blog()->triggerBlog();
    }

    public function addCategory(string $title): int
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('blog_id', dotclear()->blog()->id);
        $cur->setField('link_desc', $title);
        $cur->setField('link_href', '');
        $cur->setField('link_title', '');

        if ('' == $cur->getField('link_desc')) {
            throw new ModuleException(__('You must provide a category title'));
        }

        $cur->set('link_id', dotclear()->con()->select(
            'SELECT MAX(link_id) FROM ' . dotclear()->prefix . $this->table
        )->fInt() + 1);

        $cur->insert();
        dotclear()->blog()->triggerBlog();

        return $cur->fInt('link_id');
    }

    public function delItem(int $id): void
    {
        $strReq = 'DELETE FROM ' . dotclear()->prefix . $this->table . ' ' .
        "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' " .
            'AND link_id = ' . $id . ' ';

        dotclear()->con()->execute($strReq);
        dotclear()->blog()->triggerBlog();
    }

    public function updateOrder(int $id, int $position): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . $this->table);
        $cur->setField('link_position', $position);

        $cur->update('WHERE link_id = ' . $id .
            " AND blog_id = '" .dotclear()->con()->escape(dotclear()->blog()->id) . "'");
        dotclear()->blog()->triggerBlog();
    }

    private function setLinksData(StaticRecord $rs): void
    {
        $cat_title = null;
        while ($rs->fetch()) {
            $rs->set('is_cat', !$rs->f('link_title') && !$rs->f('link_href'));

            if ($rs->f('is_cat')) {
                $cat_title = $rs->f('link_desc');
                $rs->set('cat_title', null);
            } else {
                $rs->set('cat_title', $cat_title);
            }
        }
        $rs->moveStart();
    }

    public function getLinksHierarchy(Record $rs): array
    {
        $res = [];

        foreach ($rs->rows() as $k => $v) {
            if (!$v['is_cat']) {
                $res[$v['cat_title']][] = $v;
            }
        }

        return $res;
    }
}
