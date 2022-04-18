<?php
/**
 * @note Dotclear\Plugin\Blogroll\Common\Blogroll
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginBlogroll
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Common;

use ArrayObject;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\ModuleException;

class Blogroll
{
    private $table = 'link';

    public function getLinks(array|ArrayObject $params = []): Record
    {
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->columns([
                'link_id',
                'link_title',
                'link_desc',
                'link_href',
                'link_lang',
                'link_xfn',
                'link_position',
            ])
            ->where('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->from(dotclear()->prefix . $this->table)
            ->order('link_position')
        ;

        if (isset($params['link_id'])) {
            $sql->and('link_id = ' . (int) $params['link_id']);
        }

        $rs = $sql->select();
        $rs = $rs->toStatic();

        $this->setLinksData($rs);

        return $rs;
    }

    public function getLangs(array|ArrayObject $params = []): Record
    {
        // Use post_lang as an alias of link_lang to be able to use the dcAdminCombos::getLangsCombo() function
        $sql = new SelectStatement(__METHOD__);
        $sql
            ->columns([
                $sql->count('link_id', 'nb_link'),
                'link_lang as post_lang',
            ])
            ->from(dotclear()->prefix . $this->table)
            ->where('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->and("link_id <> ''")
            ->and('link_id IS NOT NULL')
            ->order('link_lang ' . (!empty($params['order']) && preg_match('/^(desc|asc)$/i', $params['order']) ? $params['order'] : 'desc'))
        ;

        if (isset($params['lang'])) {
            $sql->and('link_lang = ' . $sql->quote($params['lang']));
        }

        return $sql->select();
    }

    public function getLink(int $id): Record
    {
        return $this->getLinks(['link_id' => $id]);
    }

    public function addLink(string $title, string $href, string $desc = '', string $lang = '', string $xfn = ''): void
    {
        if ('' == trim($title)) {
            throw new ModuleException(__('You must provide a link title'));
        }

        if ('' == trim($href)) {
            throw new ModuleException(__('You must provide a link URL'));
        }

        $sql = new InsertStatement(__METHOD__);
        $sql
            ->columns([
                'blog_id',
                'link_title',
                'link_href',
                'link_desc',
                'link_lang',
                'link_xfn',
                'link_id',
            ])
            ->line([[
                $sql->quote(dotclear()->blog()->id),
                $sql->quote($title),
                $sql->quote($href),
                $sql->quote($desc),
                $sql->quote($lang),
                $sql->quote($xfn),
                SelectStatement::init(__METHOD__)
                    ->from(dotclear()->prefix . $this->table)
                    ->column($sql->max('link_id'))
                    ->select()
                    ->fInt() + 1,
            ]])
            ->from(dotclear()->prefix . $this->table)
            ->insert()
        ;

        dotclear()->blog()->triggerBlog();
    }

    public function updateLink(int $id, string $title, string $href, string $desc = '', string $lang = '', string $xfn = ''): void
    {
        if ('' == trim($title)) {
            throw new ModuleException(__('You must provide a link title'));
        }

        if ('' == trim($href)) {
            throw new ModuleException(__('You must provide a link URL'));
        }

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->sets([
                'link_title = ' . $sql->quote($title),
                'link_href = ' . $sql->quote($href),
                'link_desc = ' . $sql->quote($desc),
                'link_lang = ' . $sql->quote($lang),
                'link_xfn = ' . $sql->quote($xfn),
            ])
            ->where('link_id = ' . $id)
            ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->from(dotclear()->prefix . $this->table)
            ->update()
        ;

        dotclear()->blog()->triggerBlog();
    }

    public function updateCategory(int $id, string $desc): void
    {
        if ('' == trim($desc)) {
            throw new ModuleException(__('You must provide a category title'));
        }

        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('link_desc = ' . $sql->quote($desc))
            ->where('link_id = ' . $id)
            ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->from(dotclear()->prefix . $this->table)
            ->update()
        ;

        dotclear()->blog()->triggerBlog();
    }

    public function addCategory(string $title): int
    {
        if ('' == trim($title)) {
            throw new ModuleException(__('You must provide a category title'));
        }

        $sql = new InsertStatement(__METHOD__);

        $id = SelectStatement::init(__METHOD__)
            ->from(dotclear()->prefix . $this->table)
            ->column($sql->max('link_id'))
            ->select()
            ->fInt() + 1;

        $sql
            ->columns([
                'blog_id',
                'link_title',
                'link_href',
                'link_desc',
                'link_id',
            ])
            ->line([[
                $sql->quote(dotclear()->blog()->id),
                $sql->quote(''),
                $sql->quote(''),
                $sql->quote($title),
                $id,
            ]])
            ->from(dotclear()->prefix . $this->table)
            ->insert()
        ;

        dotclear()->blog()->triggerBlog();

        return $id;
    }

    public function delItem(int $id): void
    {
        $sql = new DeleteStatement(__METHOD__);
        $sql
            ->where('link_id = ' . $id)
            ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->from(dotclear()->prefix . $this->table)
            ->delete()
        ;

        dotclear()->blog()->triggerBlog();
    }

    public function updateOrder(int $id, int $position): void
    {
        $sql = new UpdateStatement(__METHOD__);
        $sql
            ->set('link_position = ' . $position)
            ->where('link_id = ' . $id)
            ->and('blog_id = ' . $sql->quote(dotclear()->blog()->id))
            ->from(dotclear()->prefix . $this->table)
            ->update()
        ;

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
