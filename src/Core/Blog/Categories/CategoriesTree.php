<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Blog\Categories;

// Dotclear\Core\Blog\Categories\CategoriesTree
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\DatabaseException;
use Exception;

/**
 * Categories tree handling.
 * 
 * CategoriesTree class is based on excellent work of Kuzma Feskov
 * (http://php.russofile.ru/ru/authors/sql/nestedsets01/)
 *
 * @ingroup  Core Category
 */
class CategoriesTree
{
    /**
     * Gets the category children.
     *
     * @param int               $start  The start
     * @param null|int          $id     The identifier
     * @param string            $sort   The sort
     * @param array<int,string> $fields The fields
     *
     * @return Record The children
     */
    public function getChildren(int $start = 0, ?int $id = null, string $sort = 'asc', array $fields = []): Record
    {
        $from   = $where = $having = '';
        $fields = ', C2.' . implode(', C2.', array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields));

        $sql = 
            'SELECT C2.cat_id, C2.cat_lft, C2.cat_rgt, COUNT(C1.cat_id) AS level ' .
            $fields . ' ' .
            'FROM ' . App::core()->prefix() . 'category AS C1, ' . App::core()->prefix() . 'category AS C2 %s ' .
            'WHERE C2.cat_lft BETWEEN C1.cat_lft AND C1.cat_rgt ' .
            ' %s ' .
            $this->getCondition('AND', 'C2.') .
            $this->getCondition('AND', 'C1.') .
            'GROUP BY C2.cat_id, C2.cat_lft, C2.cat_rgt ' . $fields . ' ' .
            ' %s ' .
            'ORDER BY C2.cat_lft ' . ('asc' == $sort ? 'ASC' : 'DESC') . ' '
        ;

        if (0 < $start) {
            $from  = ', ' . App::core()->prefix() . 'category AS C3';
            $where = 'AND C3.cat_id = ' . $start . ' AND C1.cat_lft >= C3.cat_lft AND C1.cat_rgt <= C3.cat_rgt';
            $where .= $this->getCondition('AND', 'C3.');
        }

        if (null !== $id) {
            $having = ' HAVING C2.cat_id = ' . $id;
        }

        $sql = sprintf($sql, $from, $where, $having);

        return App::core()->con()->select($sql);
    }

    /**
     * Gets the parents.
     *
     * @param int               $id     The identifier
     * @param array<int,string> $fields The fields
     *
     * @return Record The parents
     */
    public function getParents(int $id, array $fields = []): Record
    {
        return App::core()->con()->select(
            'SELECT C1.cat_id, C1.' . implode(', C1.', array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields)) . ' ' .
            'FROM ' . App::core()->prefix() . 'category C1, ' . App::core()->prefix() . 'category C2 ' .
            'WHERE C2.cat_id = ' . $id . ' ' .
            'AND C1.cat_lft < C2.cat_lft ' .
            'AND C1.cat_rgt > C2.cat_rgt ' .
            $this->getCondition('AND', 'C2.') .
            $this->getCondition('AND', 'C1.') .
            'ORDER BY C1.cat_lft ASC '
        );
    }

    /**
     * Gets the parent.
     *
     * @param int               $id     The identifier
     * @param array<int,string> $fields The fields
     *
     * @return Record The parent
     */
    public function getParent(int $id, array $fields = []): Record
    {
        return App::core()->con()->select(
            'SELECT C1.cat_id, C1.' . implode(', C1.', array_merge(['cat_title', 'cat_url', 'cat_desc'], $fields)) . ' ' .
            'FROM ' . App::core()->prefix() . 'category C1, ' . App::core()->prefix() . 'category C2 ' .
            'WHERE C2.cat_id = ' . $id . ' ' .
            'AND C1.cat_lft < C2.cat_lft ' .
            'AND C1.cat_rgt > C2.cat_rgt ' .
            $this->getCondition('AND', 'C2.') .
            $this->getCondition('AND', 'C1.') .
            'ORDER BY C1.cat_lft DESC ' .
            App::core()->con()->limit(1)
        );
    }


    // / @name Tree manipulation
    // @{
    /**
     * Adds a node.
     *
     * @param array|Cursor $data   The data
     * @param int   $target The target
     *
     * @throws DatabaseException
     */
    public function addNode(array|Cursor $data, int $target = 0): int|false
    {
        if (is_array($data)) {
            $D    = $data;
            $data = App::core()->con()->openCursor(App::core()->prefix() . 'category');
            foreach ($D as $k => $v) {
                $data->setField($k, $v);
            }
            unset($D);
        }

        // We want to put it at the end
        App::core()->con()->writeLock(App::core()->prefix() . 'category');

        try {
            $rs = App::core()->con()->select('SELECT MAX(cat_id) as n_id FROM ' . App::core()->prefix() . 'category');
            $id = $rs->fInt('n_id');

            $rs = App::core()->con()->select(
                'SELECT MAX(cat_rgt) as n_r ' .
                'FROM ' . App::core()->prefix() . 'category' .
                $this->getCondition('WHERE')
            );
            $last = $rs->fInt('n_r') == 0 ? 1 : $rs->fInt('n_r');

            $data->setField('cat_id', $id      + 1);
            $data->setField('cat_lft', $last  + 1);
            $data->setField('cat_rgt', $last + 2);

            $data->insert();
            App::core()->con()->unlock();

            try {
                $this->setNodeParent($id + 1, $target);

                return $data->getField('cat_id');
            } catch (DatabaseException) {
            } // We don't mind error in this case
        } catch (Exception $e) {
            App::core()->con()->unlock();

            throw $e;
        }

        return false;
    }

    /**
     * Update position.
     *
     * @param int $id    The identifier
     * @param int $left  The left
     * @param int $right The right
     */
    public function updatePosition(int $id, int $left, int $right): void
    {
        $sql = 
            'UPDATE ' . App::core()->prefix() . 'category SET ' .
            'cat_lft = ' . $left . ', ' .
            'cat_rgt = ' . $right . ' ' .
            'WHERE cat_id = ' . $id .
            $this->getCondition()
        ;

        App::core()->con()->begin();

        try {
            App::core()->con()->execute($sql);
            App::core()->con()->commit();
        } catch (Exception $e) {
            App::core()->con()->rollback();

            throw $e;
        }
    }

    /**
     * Delete a node.
     *
     * @param int  $node          The node
     * @param bool $keep_children keep children
     *
     * @throws DatabaseException
     */
    public function deleteNode(int $node, bool $keep_children = true): void
    {
        $rs = $this->getChildren(0, $node);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $node_left  = $rs->fInt('cat_lft');
        $node_right = $rs->fInt('cat_rgt');

        try {
            App::core()->con()->begin();

            if ($keep_children) {
                App::core()->con()->execute('DELETE FROM ' . App::core()->prefix() . 'category WHERE cat_id = ' . $node);

                $sql = 
                    'UPDATE ' . App::core()->prefix() . 'category SET ' .
                    'cat_rgt = CASE ' .
                    'WHEN cat_rgt BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                    'THEN cat_rgt - 1 ' .
                    'WHEN cat_rgt > ' . $node_right . ' ' .
                    'THEN cat_rgt - 2 ' .
                    'ELSE cat_rgt ' .
                    'END, ' .
                    'cat_lft = CASE ' .
                    'WHEN cat_lft BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                    'THEN cat_lft - 1 ' .
                    'WHEN cat_lft > ' . $node_right . ' ' .
                    'THEN cat_lft - 2 ' .
                    'ELSE cat_lft ' .
                    'END ' .
                    'WHERE cat_rgt > ' . $node_left .
                    $this->getCondition()
                ;

                App::core()->con()->execute($sql);
            } else {
                App::core()->con()->execute('DELETE FROM ' . App::core()->prefix() . 'category WHERE cat_lft BETWEEN ' . $node_left . ' AND ' . $node_right);

                $node_delta = $node_right - $node_left + 1;
                $sql        = 'UPDATE ' . App::core()->prefix() . 'category SET '
                . 'cat_lft = CASE '
                . 'WHEN cat_lft > ' . $node_left . ' '
                . 'THEN cat_lft - (' . $node_delta . ') '
                . 'ELSE cat_lft '
                . 'END, '
                . 'cat_rgt = CASE '
                . 'WHEN cat_rgt > ' . $node_left . ' '
                . 'THEN cat_rgt - (' . $node_delta . ') '
                . 'ELSE cat_rgt '
                . 'END '
                . 'WHERE cat_rgt > ' . $node_right
                . $this->getCondition();
            }

            App::core()->con()->commit();
        } catch (Exception $e) {
            App::core()->con()->rollback();

            throw $e;
        }
    }

    /**
     * Reset order.
     */
    public function resetOrder(): void
    {
        $rs = App::core()->con()->select(
            'SELECT cat_id '
            . 'FROM ' . App::core()->prefix() . 'category '
            . $this->getCondition('WHERE')
            . 'ORDER BY cat_lft ASC '
        );

        $lft = 2;
        App::core()->con()->begin();

        try {
            while ($rs->fetch()) {
                App::core()->con()->execute(
                    'UPDATE ' . App::core()->prefix() . 'category SET '
                    . 'cat_lft = ' . ($lft++) . ', '
                    . 'cat_rgt = ' . ($lft++) . ' '
                    . 'WHERE cat_id = ' . $rs->fInt('cat_id') . ' '
                    . $this->getCondition()
                );
            }
            App::core()->con()->commit();
        } catch (Exception $e) {
            App::core()->con()->rollback();

            throw $e;
        }
    }

    /**
     * Sets the node parent.
     *
     * @param int $node   The node
     * @param int $target The target
     *
     * @throws DatabaseException
     */
    public function setNodeParent(int $node, int $target = 0): void
    {
        if ($node == $target) {
            return;
        }

        $rs = $this->getChildren(0, $node);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $node_left  = $rs->fInt('cat_lft');
        $node_right = $rs->fInt('cat_rgt');
        $node_level = $rs->fInt('level');

        if (0 < $target) {
            $rs = $this->getChildren(0, $target);
        } else {
            $rs = App::core()->con()->select(
                'SELECT MIN(cat_lft)-1 AS cat_lft, MAX(cat_rgt)+1 AS cat_rgt, 0 AS level ' .
                'FROM ' . App::core()->prefix() . 'category ' .
                $this->getCondition('WHERE')
            );
        }
        $target_left  = $rs->fInt('cat_lft');
        $target_right = $rs->fInt('cat_rgt');
        $target_level = $rs->fInt('level');

        if ($node_left == $target_left
            || ($target_left >= $node_left && $target_left <= $node_right)
            || ($target_level + 1 == $node_level && $node_left > $target_left && $node_right < $target_right)
        ) {
            throw new DatabaseException('Cannot move tree');
        }

        if ($target_left < $node_left && $target_right > $node_right && $node_level - 1 > $target_level) {
            $sql = 
                'UPDATE ' . App::core()->prefix() . 'category SET ' .
                'cat_rgt = CASE ' .
                'WHEN cat_rgt BETWEEN ' . ($node_right + 1) . ' AND ' . ($target_right - 1) . ' ' .
                'THEN cat_rgt-(' . ($node_right - $node_left + 1) . ') ' .
                'WHEN cat_lft BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                'THEN cat_rgt+' . ((($target_right - $node_right - $node_level + $target_level) / 2) * 2 + $node_level - $target_level - 1) . ' ' .
                'ELSE ' .
                'cat_rgt ' .
                'END, ' .
                'cat_lft = CASE ' .
                'WHEN cat_lft BETWEEN ' . ($node_right + 1) . ' AND ' . ($target_right - 1) . ' ' .
                'THEN cat_lft-(' . ($node_right - $node_left + 1) . ') ' .
                'WHEN cat_lft BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                'THEN cat_lft+' . ((($target_right - $node_right - $node_level + $target_level) / 2) * 2 + $node_level - $target_level - 1) . ' ' .
                'ELSE cat_lft ' .
                'END ' .
                'WHERE cat_lft BETWEEN ' . ($target_left + 1) . ' AND ' . ($target_right - 1) . ''
            ;
        } elseif ($target_left < $node_left) {
            $sql = 
                'UPDATE ' . App::core()->prefix() . 'category SET ' .
                'cat_lft = CASE ' .
                'WHEN cat_lft BETWEEN ' . $target_right . ' AND ' . ($node_left - 1) . ' ' .
                'THEN cat_lft+' . ($node_right - $node_left + 1) . ' ' .
                'WHEN cat_lft BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                'THEN cat_lft-(' . ($node_left - $target_right) . ') ' .
                'ELSE cat_lft ' .
                'END, ' .
                'cat_rgt = CASE ' .
                'WHEN cat_rgt BETWEEN ' . $target_right . ' AND ' . $node_left . ' ' .
                'THEN cat_rgt+' . ($node_right - $node_left + 1) . ' ' .
                'WHEN cat_rgt BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                'THEN cat_rgt-(' . ($node_left - $target_right) . ') ' .
                'ELSE cat_rgt ' .
                'END ' .
                'WHERE (cat_lft BETWEEN ' . $target_left . ' AND ' . $node_right . ' ' .
                'OR cat_rgt BETWEEN ' . $target_left . ' AND ' . $node_right . ')'
            ;
        } else {
            $sql = 
                'UPDATE ' . App::core()->prefix() . 'category SET ' .
                'cat_lft = CASE ' .
                'WHEN cat_lft BETWEEN ' . $node_right . ' AND ' . $target_right . ' ' .
                'THEN cat_lft-' . ($node_right - $node_left + 1) . ' ' .
                'WHEN cat_lft BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                'THEN cat_lft+' . ($target_right - 1 - $node_right) . ' ' .
                'ELSE cat_lft ' .
                'END, ' .
                'cat_rgt = CASE ' .
                'WHEN cat_rgt BETWEEN ' . ($node_right + 1) . ' AND ' . ($target_right - 1) . ' ' .
                'THEN cat_rgt-' . ($node_right - $node_left + 1) . ' ' .
                'WHEN cat_rgt BETWEEN ' . $node_left . ' AND ' . $node_right . ' ' .
                'THEN cat_rgt+' . ($target_right - 1 - $node_right) . ' ' .
                'ELSE cat_rgt ' .
                'END ' .
                'WHERE (cat_lft BETWEEN ' . $node_left . ' AND ' . $target_right . ' ' .
                'OR cat_rgt BETWEEN ' . $node_left . ' AND ' . $target_right . ')'
            ;
        }

        $sql .= ' ' . $this->getCondition();

        App::core()->con()->execute($sql);
    }

    /**
     * Sets the node position.
     *
     * @param int    $nodeA    The node a
     * @param int    $nodeB    The node b
     * @param string $position The position
     *
     * @throws DatabaseException
     */
    public function setNodePosition(int $nodeA, int $nodeB, string $position = 'after'): void
    {
        $rs = $this->getChildren(0, $nodeA);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $A_left  = $rs->fInt('cat_lft');
        $A_right = $rs->fInt('cat_rgt');
        $A_level = $rs->fInt('level');

        $rs = $this->getChildren(0, $nodeB);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $B_left  = $rs->fInt('cat_lft');
        $B_right = $rs->fInt('cat_rgt');
        $B_level = $rs->fInt('level');

        if ($A_level != $B_level) {
            throw new DatabaseException('Cannot change position');
        }

        $rs      = $this->getParents($nodeA);
        $parentA = $rs->isEmpty() ? 0 : $rs->fInt('cat_id');
        $rs      = $this->getParents($nodeB);
        $parentB = $rs->isEmpty() ? 0 : $rs->fInt('cat_id');

        if ($parentA != $parentB) {
            throw new DatabaseException('Cannot change position');
        }

        if ('before' == $position) {
            if ($A_left > $B_left) {
                $sql = 
                    'UPDATE ' . App::core()->prefix() . 'category SET ' .
                    'cat_rgt = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_rgt - (' . ($A_left - $B_left) . ') ' .
                    'WHEN cat_lft BETWEEN ' . $B_left . ' AND ' . ($A_left - 1) . 
                    ' THEN cat_rgt +  ' . ($A_right - $A_left + 1) . ' ELSE cat_rgt END, ' .
                    'cat_lft = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_lft - (' . ($A_left   - $B_left) . ') ' .
                    'WHEN cat_lft BETWEEN ' . $B_left . ' AND ' . ($A_left - 1) . 
                    ' THEN cat_lft + ' . ($A_right - $A_left + 1) . ' ELSE cat_lft END ' .
                    'WHERE cat_lft BETWEEN ' . $B_left . ' AND ' . $A_right
                ;
            } else {
                $sql = 
                    'UPDATE ' . App::core()->prefix() . 'category SET ' .
                    'cat_rgt = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_rgt + ' . (($B_left - $A_left) - ($A_right - $A_left + 1)) . ' ' .
                    'WHEN cat_lft BETWEEN ' . ($A_right + 1) . ' AND ' . ($B_left - 1) . 
                    ' THEN cat_rgt - (' . (($A_right - $A_left + 1)) . ') ELSE cat_rgt END, ' .
                    'cat_lft = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_lft + ' . (($B_left - $A_left) - ($A_right - $A_left + 1)) . ' ' .
                    'WHEN cat_lft BETWEEN ' . ($A_right + 1) . ' AND ' . ($B_left - 1) . 
                    ' THEN cat_lft - (' . ($A_right - $A_left + 1) . ') ELSE cat_lft END ' .
                    'WHERE cat_lft BETWEEN ' . $A_left . ' AND ' . ($B_left - 1)
                ;
            }
        } else {
            if ($A_left > $B_left) {
                $sql = 
                    'UPDATE ' . App::core()->prefix() . 'category SET ' .
                    'cat_rgt = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_rgt - (' . ($A_left - $B_left - ($B_right - $B_left + 1)) . ') ' .
                    'WHEN cat_lft BETWEEN ' . ($B_right + 1) . ' AND ' . ($A_left - 1) . 
                    ' THEN cat_rgt +  ' . ($A_right - $A_left + 1) . ' ELSE cat_rgt END, ' .
                    'cat_lft = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_lft - (' . ($A_left - $B_left - ($B_right - $B_left + 1)) . ') ' .
                    'WHEN cat_lft BETWEEN ' . ($B_right + 1) . ' AND ' . ($A_left - 1) . 
                    ' THEN cat_lft + ' . ($A_right - $A_left + 1) . ' ELSE cat_lft END ' .
                    'WHERE cat_lft BETWEEN ' . ($B_right + 1) . ' AND ' . $A_right
                ;
            } else {
                $sql = 
                    'UPDATE ' . App::core()->prefix() . 'category SET ' .
                    'cat_rgt = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_rgt + ' . ($B_right - $A_right) . ' ' .
                    'WHEN cat_lft BETWEEN ' . ($A_right + 1) . ' AND ' . $B_right . 
                    ' THEN cat_rgt - (' . (($A_right - $A_left + 1)) . ') ELSE cat_rgt END, ' .
                    'cat_lft = CASE WHEN cat_lft BETWEEN ' . $A_left . ' AND ' . $A_right . 
                    ' THEN cat_lft + ' . ($B_right   - $A_right) . ' ' .
                    'WHEN cat_lft BETWEEN ' . ($A_right + 1) . ' AND ' . $B_right . 
                    ' THEN cat_lft - (' . ($A_right - $A_left + 1) . ') ELSE cat_lft END ' .
                    'WHERE cat_lft BETWEEN ' . $A_left . ' AND ' . $B_right
                ;
            }
        }

        $sql .= $this->getCondition();
        App::core()->con()->execute($sql);
    }

    /**
     * Gets the condition.
     *
     * @param string $start  The start
     * @param string $prefix The prefix
     *
     * @return string the condition
     */
    protected function getCondition(string $start = 'AND', string $prefix = ''): string
    {
        return ' ' . $start . ' ' . $prefix . "blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "' ";
    }
    // @}
}
