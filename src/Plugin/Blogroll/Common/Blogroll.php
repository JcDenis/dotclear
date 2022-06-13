<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Blogroll\Common;

// Dotclear\Plugin\Blogroll\Common\Blogroll
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Blog\Posts\LangsParam;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\InsertStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\ModuleException;

/**
 * Blogroll handling methods.
 *
 * @ingroup  Plugin Blogroll
 */
class Blogroll
{
    public function getLinks(array|ArrayObject $params = []): Record
    {
        $sql = new SelectStatement();
        $sql->columns([
            'link_id',
            'link_title',
            'link_desc',
            'link_href',
            'link_lang',
            'link_xfn',
            'link_position',
        ]);
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->from(App::core()->prefix() . 'link');
        $sql->order('link_position');

        if (isset($params['link_id'])) {
            $sql->and('link_id = ' . (int) $params['link_id']);
        }

        $record = $sql->select();
        $record = $record->toStatic();

        $this->setLinksData($record);

        return $record;
    }

    public function getLangs(Param $param = null): Record
    {
        // Use post_lang as an alias of link_lang to be able to use the dcAdminCombos::getLangsCombo() function
        $param = new LangsParam($param);

        $sql = new SelectStatement();
        $sql->columns([
            $sql->count('link_id', 'nb_link'),
            'link_lang as post_lang',
        ]);
        $sql->from(App::core()->prefix() . 'link');
        $sql->where('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->and("link_id <> ''");
        $sql->and('link_id IS NOT NULL');
        $sql->order('link_lang ' . (!empty($param->order()) && preg_match('/^(desc|asc)$/i', $param->order()) ? $param->order() : 'desc'));

        if (null !== $param->post_lang()) {
            $sql->and('link_lang = ' . $sql->quote($param->post_lang()));
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

        $sql = new SelectStatement();
        $sql->from(App::core()->prefix() . 'link');
        $sql->column($sql->max('link_id'));
        $id = $sql->select()->fInt() + 1;

        $sql = new InsertStatement();
        $sql->columns([
            'blog_id',
            'link_title',
            'link_href',
            'link_desc',
            'link_lang',
            'link_xfn',
            'link_id',
        ]);
        $sql->line([[
            $sql->quote(App::core()->blog()->id),
            $sql->quote($title),
            $sql->quote($href),
            $sql->quote($desc),
            $sql->quote($lang),
            $sql->quote($xfn),
            $id,
        ]]);
        $sql->from(App::core()->prefix() . 'link');
        $sql->insert();

        App::core()->blog()->triggerBlog();
    }

    public function updateLink(int $id, string $title, string $href, string $desc = '', string $lang = '', string $xfn = ''): void
    {
        if ('' == trim($title)) {
            throw new ModuleException(__('You must provide a link title'));
        }

        if ('' == trim($href)) {
            throw new ModuleException(__('You must provide a link URL'));
        }

        $sql = new UpdateStatement();
        $sql->sets([
            'link_title = ' . $sql->quote($title),
            'link_href = ' . $sql->quote($href),
            'link_desc = ' . $sql->quote($desc),
            'link_lang = ' . $sql->quote($lang),
            'link_xfn = ' . $sql->quote($xfn),
        ]);
        $sql->where('link_id = ' . $id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->from(App::core()->prefix() . 'link');
        $sql->update();

        App::core()->blog()->triggerBlog();
    }

    public function updateCategory(int $id, string $desc): void
    {
        if ('' == trim($desc)) {
            throw new ModuleException(__('You must provide a category title'));
        }

        $sql = new UpdateStatement();
        $sql->set('link_desc = ' . $sql->quote($desc));
        $sql->where('link_id = ' . $id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->from(App::core()->prefix() . 'link');
        $sql->update();

        App::core()->blog()->triggerBlog();
    }

    public function addCategory(string $title): int
    {
        if ('' == trim($title)) {
            throw new ModuleException(__('You must provide a category title'));
        }

        $sql = new SelectStatement();
        $sql->from(App::core()->prefix() . 'link');
        $sql->column($sql->max('link_id'));
        $id = $sql->select()->fInt() + 1;

        $sql = new InsertStatement();
        $sql->columns([
            'blog_id',
            'link_title',
            'link_href',
            'link_desc',
            'link_id',
        ]);
        $sql->line([[
            $sql->quote(App::core()->blog()->id),
            $sql->quote(''),
            $sql->quote(''),
            $sql->quote($title),
            $id,
        ]]);
        $sql->from(App::core()->prefix() . 'link');
        $sql->insert();

        App::core()->blog()->triggerBlog();

        return $id;
    }

    public function delItem(int $id): void
    {
        $sql = new DeleteStatement();
        $sql->where('link_id = ' . $id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->from(App::core()->prefix() . 'link');
        $sql->delete();

        App::core()->blog()->triggerBlog();
    }

    public function updateOrder(int $id, int $position): void
    {
        $sql = new UpdateStatement();
        $sql->set('link_position = ' . $position);
        $sql->where('link_id = ' . $id);
        $sql->and('blog_id = ' . $sql->quote(App::core()->blog()->id));
        $sql->from(App::core()->prefix() . 'link');
        $sql->update();

        App::core()->blog()->triggerBlog();
    }

    private function setLinksData(StaticRecord $record): void
    {
        $cat_title = null;
        while ($record->fetch()) {
            $record->set('is_cat', !$record->f('link_title') && !$record->f('link_href'));

            if ($record->f('is_cat')) {
                $cat_title = $record->f('link_desc');
                $record->set('cat_title', null);
            } else {
                $record->set('cat_title', $cat_title);
            }
        }
        $record->moveStart();
    }

    public function getLinksHierarchy(Record $record): array
    {
        $res = [];

        foreach ($record->rows() as $k => $v) {
            if (!$v['is_cat']) {
                $res[$v['cat_title']][] = $v;
            }
        }

        return $res;
    }
}
