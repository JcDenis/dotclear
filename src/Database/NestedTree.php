<?php
/**
 * @class Dotclear\Database\NestedTree
 * @brief Database helper to construct nested tree
 *
 * nestedTree class is based on excellent work of Kuzma Feskov
 * (http://php.russofile.ru/ru/authors/sql/nestedsets01/)
 *
 * @package Dotclear
 * @subpackage Database
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Database;

use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\DatabaseException;

abstract class NestedTree
{
    protected $table;
    protected $f_left;
    protected $f_right;
    protected $f_id;

    protected $add_condition = [];

    protected $parents;

    /**
     * Constructs a new instance.
     *
     * @param      AbstractConnection  $con    The con
     */
    public function __construct(protected AbstractConnection $con)
    {
    }

    /**
     * Gets the children.
     *
     * @param   int         $start      The start
     * @param   int|null    $id         The identifier
     * @param   string      $sort       The sort
     * @param   array       $fields     The fields
     *
     * @return  Record                  The children.
     */
    public function getChildren(int $start = 0, ?int $id = null, string $sort = 'asc', array $fields = []): Record
    {
        $fields = count($fields) > 0 ? ', C2.' . implode(', C2.', $fields) : '';

        $sql = 'SELECT C2.' . $this->f_id . ', C2.' . $this->f_left . ', C2.' . $this->f_right . ', COUNT(C1.' . $this->f_id . ') AS level '
        . $fields . ' '
        . 'FROM ' . $this->table . ' AS C1, ' . $this->table . ' AS C2 %s '
        . 'WHERE C2.' . $this->f_left . ' BETWEEN C1.' . $this->f_left . ' AND C1.' . $this->f_right . ' '
        . ' %s '
        . $this->getCondition('AND', 'C2.')
        . $this->getCondition('AND', 'C1.')
        . 'GROUP BY C2.' . $this->f_id . ', C2.' . $this->f_left . ', C2.' . $this->f_right . ' ' . $fields . ' '
        . ' %s '
        . 'ORDER BY C2.' . $this->f_left . ' ' . ($sort == 'asc' ? 'ASC' : 'DESC') . ' ';

        $from = $where = '';
        if (0 < $start) {
            $from  = ', ' . $this->table . ' AS C3';
            $where = 'AND C3.' . $this->f_id . ' = ' . $start . ' AND C1.' . $this->f_left . ' >= C3.' . $this->f_left . ' AND C1.' . $this->f_right . ' <= C3.' . $this->f_right;
            $where .= $this->getCondition('AND', 'C3.');
        }

        $having = '';
        if (null !== $id) {
            $having = ' HAVING C2.' . $this->f_id . ' = ' . $id;
        }

        $sql = sprintf($sql, $from, $where, $having);

        return $this->con->select($sql);
    }

    /**
     * Gets the parents.
     *
     * @param   int     $id         The identifier
     * @param   array   $fields     The fields
     *
     * @return  Record              The parents.
     */
    public function getParents(int $id, array $fields = []): Record
    {
        $fields = count($fields) > 0 ? ', C1.' . implode(', C1.', $fields) : '';

        return $this->con->select(
            'SELECT C1.' . $this->f_id . ' ' . $fields . ' '
            . 'FROM ' . $this->table . ' C1, ' . $this->table . ' C2 '
            . 'WHERE C2.' . $this->f_id . ' = ' . $id . ' '
            . 'AND C1.' . $this->f_left . ' < C2.' . $this->f_left . ' '
            . 'AND C1.' . $this->f_right . ' > C2.' . $this->f_right . ' '
            . $this->getCondition('AND', 'C2.')
            . $this->getCondition('AND', 'C1.')
            . 'ORDER BY C1.' . $this->f_left . ' ASC '
        );
    }

    /**
     * Gets the parent.
     *
     * @param   int     $id         The identifier
     * @param   array   $fields     The fields
     *
     * @return  Record              The parent.
     */
    public function getParent(int $id, array $fields = []): Record
    {
        $fields = count($fields) > 0 ? ', C1.' . implode(', C1.', $fields) : '';

        return $this->con->select(
            'SELECT C1.' . $this->f_id . ' ' . $fields . ' '
            . 'FROM ' . $this->table . ' C1, ' . $this->table . ' C2 '
            . 'WHERE C2.' . $this->f_id . ' = ' . $id . ' '
            . 'AND C1.' . $this->f_left . ' < C2.' . $this->f_left . ' '
            . 'AND C1.' . $this->f_right . ' > C2.' . $this->f_right . ' '
            . $this->getCondition('AND', 'C2.')
            . $this->getCondition('AND', 'C1.')
            . 'ORDER BY C1.' . $this->f_left . ' DESC '
            . $this->con->limit(1)
        );
    }

    /* ------------------------------------------------
     * Tree manipulations
     * ---------------------------------------------- */
    /**
     * Adds a node.
     *
     * @param   mixed   $data       The data
     * @param   int     $target     The target
     *
     * @throws  Exception
     *
     * @return  mixed
     */
    public function addNode(mixed $data, int $target = 0): mixed
    {
        if (!is_array($data) && !($data instanceof cursor)) {
            throw new DatabaseException('Invalid data block');
        }

        if (is_array($data)) {
            $D    = $data;
            $data = $this->con->openCursor($this->table);
            foreach ($D as $k => $v) {
                $data->{$k} = $v;
            }
            unset($D);
        }

        # We want to put it at the end
        $this->con->writeLock($this->table);

        try {
            $rs = $this->con->select('SELECT MAX(' . $this->f_id . ') as n_id FROM ' . $this->table);
            $id = $rs->n_id;

            $rs = $this->con->select(
                'SELECT MAX(' . $this->f_right . ') as n_r ' .
                'FROM ' . $this->table .
                $this->getCondition('WHERE')
            );
            $last = $rs->n_r == 0 ? 1 : $rs->n_r;

            $data->{$this->f_id}    = $id   + 1;
            $data->{$this->f_left}  = $last + 1;
            $data->{$this->f_right} = $last + 2;

            $data->insert();
            $this->con->unlock();

            try {
                $this->setNodeParent($id + 1, $target);

                return $data->{$this->f_id};
            } catch (DatabaseException) {
            } # We don't mind error in this case
        } catch (\Exception $e) {
            $this->con->unlock();

            throw $e;
        }
    }

    /**
     * Update position
     *
     * @param   mixed   $id     The identifier
     * @param   mixed   $left   The left
     * @param   mixed   $right  The right
     */
    public function updatePosition(mixed $id, mixed $left, mixed $right): void
    {
        $node_left  = (int) $left;
        $node_right = (int) $right;
        $node_id    = (int) $id;
        $sql        = 'UPDATE ' . $this->table . ' SET '
        . $this->f_left . ' = ' . $node_left . ', '
        . $this->f_right . ' = ' . $node_right
        . ' WHERE ' . $this->f_id . ' = ' . $node_id
        . $this->getCondition();

        $this->con->begin();

        try {
            $this->con->execute($sql);
            $this->con->commit();
        } catch (\Exception $e) {
            $this->con->rollback();

            throw $e;
        }
    }

    /**
     * Delete a node
     *
     * @param   mixed   $node           The node
     * @param   bool    $keep_children  keep children
     *
     * @throws  Exception
     */
    public function deleteNode(mixed $node, bool $keep_children = true): void
    {
        $node = (int) $node;

        $rs = $this->getChildren(0, $node);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $node_left  = (int) $rs->{$this->f_left};
        $node_right = (int) $rs->{$this->f_right};

        try {
            $this->con->begin();

            if ($keep_children) {
                $this->con->execute('DELETE FROM ' . $this->table . ' WHERE ' . $this->f_id . ' = ' . $node);

                $sql = 'UPDATE ' . $this->table . ' SET '
                . $this->f_right . ' = CASE '
                . 'WHEN ' . $this->f_right . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
                . 'THEN ' . $this->f_right . ' - 1 '
                . 'WHEN ' . $this->f_right . ' > ' . $node_right . ' '
                . 'THEN ' . $this->f_right . ' - 2 '
                . 'ELSE ' . $this->f_right . ' '
                . 'END, '
                . $this->f_left . ' = CASE '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
                . 'THEN ' . $this->f_left . ' - 1 '
                . 'WHEN ' . $this->f_left . ' > ' . $node_right . ' '
                . 'THEN ' . $this->f_left . ' - 2 '
                . 'ELSE ' . $this->f_left . ' '
                . 'END '
                . 'WHERE ' . $this->f_right . ' > ' . $node_left
                . $this->getCondition();

                $this->con->execute($sql);
            } else {
                $this->con->execute('DELETE FROM ' . $this->table . ' WHERE ' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $node_right);

                $node_delta = $node_right - $node_left + 1;
                $sql        = 'UPDATE ' . $this->table . ' SET '
                . $this->f_left . ' = CASE '
                . 'WHEN ' . $this->f_left . ' > ' . $node_left . ' '
                . 'THEN ' . $this->f_left . ' - (' . $node_delta . ') '
                . 'ELSE ' . $this->f_left . ' '
                . 'END, '
                . $this->f_right . ' = CASE '
                . 'WHEN ' . $this->f_right . ' > ' . $node_left . ' '
                . 'THEN ' . $this->f_right . ' - (' . $node_delta . ') '
                . 'ELSE ' . $this->f_right . ' '
                . 'END '
                . 'WHERE ' . $this->f_right . ' > ' . $node_right
                . $this->getCondition();
            }

            $this->con->commit();
        } catch (\Exception $e) {
            $this->con->rollback();

            throw $e;
        }
    }

    /**
     * Reset order
     */
    public function resetOrder(): void
    {
        $rs = $this->con->select(
            'SELECT ' . $this->f_id . ' '
            . 'FROM ' . $this->table . ' '
            . $this->getCondition('WHERE')
            . 'ORDER BY ' . $this->f_left . ' ASC '
        );

        $lft = 2;
        $this->con->begin();

        try {
            while ($rs->fetch()) {
                $this->con->execute(
                    'UPDATE ' . $this->table . ' SET '
                    . $this->f_left . ' = ' . ($lft++) . ', '
                    . $this->f_right . ' = ' . ($lft++) . ' '
                    . 'WHERE ' . $this->f_id . ' = ' . (int) $rs->{$this->f_id} . ' '
                    . $this->getCondition()
                );
            }
            $this->con->commit();
        } catch (\Exception $e) {
            $this->con->rollback();

            throw $e;
        }
    }

    /**
     * Sets the node parent.
     *
     * @param   mixed   $node       The node
     * @param   mixed   $target     The target
     *
     * @throws  Exception
     */
    public function setNodeParent(mixed $node, mixed $target = 0): void
    {
        if ($node == $target) {
            return;
        }
        $node   = (int) $node;
        $target = (int) $target;

        $rs = $this->getChildren(0, $node);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $node_left  = (int) $rs->{$this->f_left};
        $node_right = (int) $rs->{$this->f_right};
        $node_level = (int) $rs->level;

        if ($target > 0) {
            $rs = $this->getChildren(0, $target);
        } else {
            $rs = $this->con->select(
                'SELECT MIN(' . $this->f_left . ')-1 AS ' . $this->f_left . ', MAX(' . $this->f_right . ')+1 AS ' . $this->f_right . ', 0 AS level ' .
                'FROM ' . $this->table . ' ' .
                $this->getCondition('WHERE')
            );
        }
        $target_left  = (int) $rs->{$this->f_left};
        $target_right = (int) $rs->{$this->f_right};
        $target_level = (int) $rs->level;

        if ($node_left == $target_left
            || ($target_left >= $node_left && $target_left <= $node_right)
            || ($node_level == $target_level + 1 && $node_left > $target_left && $node_right < $target_right)
        ) {
            throw new DatabaseException('Cannot move tree');
        }

        if ($target_left < $node_left && $target_right > $node_right && $target_level < $node_level - 1) {
            $sql = 'UPDATE ' . $this->table . ' SET '
            . $this->f_right . ' = CASE '
            . 'WHEN ' . $this->f_right . ' BETWEEN ' . ($node_right + 1) . ' AND ' . ($target_right - 1) . ' '
            . 'THEN ' . $this->f_right . '-(' . ($node_right - $node_left + 1) . ') '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
            . 'THEN ' . $this->f_right . '+' . ((($target_right - $node_right - $node_level + $target_level) / 2) * 2 + $node_level - $target_level - 1) . ' '
            . 'ELSE '
            . $this->f_right . ' '
            . 'END, '
            . $this->f_left . ' = CASE '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($node_right + 1) . ' AND ' . ($target_right - 1) . ' '
            . 'THEN ' . $this->f_left . '-(' . ($node_right - $node_left + 1) . ') '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
            . 'THEN ' . $this->f_left . '+' . ((($target_right - $node_right - $node_level + $target_level) / 2) * 2 + $node_level - $target_level - 1) . ' '
            . 'ELSE ' . $this->f_left . ' '
            . 'END '
            . 'WHERE ' . $this->f_left . ' BETWEEN ' . ($target_left + 1) . ' AND ' . ($target_right - 1) . '';
        } elseif ($target_left < $node_left) {
            $sql = 'UPDATE ' . $this->table . ' SET '
            . $this->f_left . ' = CASE '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . $target_right . ' AND ' . ($node_left - 1) . ' '
            . 'THEN ' . $this->f_left . '+' . ($node_right - $node_left + 1) . ' '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
            . 'THEN ' . $this->f_left . '-(' . ($node_left - $target_right) . ') '
            . 'ELSE ' . $this->f_left . ' '
            . 'END, '
            . $this->f_right . ' = CASE '
            . 'WHEN ' . $this->f_right . ' BETWEEN ' . $target_right . ' AND ' . $node_left . ' '
            . 'THEN ' . $this->f_right . '+' . ($node_right - $node_left + 1) . ' '
            . 'WHEN ' . $this->f_right . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
            . 'THEN ' . $this->f_right . '-(' . ($node_left - $target_right) . ') '
            . 'ELSE ' . $this->f_right . ' '
            . 'END '
            . 'WHERE (' . $this->f_left . ' BETWEEN ' . $target_left . ' AND ' . $node_right . ' '
            . 'OR ' . $this->f_right . ' BETWEEN ' . $target_left . ' AND ' . $node_right . ')';
        } else {
            $sql = 'UPDATE ' . $this->table . ' SET '
            . $this->f_left . ' = CASE '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . $node_right . ' AND ' . $target_right . ' '
            . 'THEN ' . $this->f_left . '-' . ($node_right - $node_left + 1) . ' '
            . 'WHEN ' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
            . 'THEN ' . $this->f_left . '+' . ($target_right - 1 - $node_right) . ' '
            . 'ELSE ' . $this->f_left . ' '
            . 'END, '
            . $this->f_right . ' = CASE '
            . 'WHEN ' . $this->f_right . ' BETWEEN ' . ($node_right + 1) . ' AND ' . ($target_right - 1) . ' '
            . 'THEN ' . $this->f_right . '-' . ($node_right - $node_left + 1) . ' '
            . 'WHEN ' . $this->f_right . ' BETWEEN ' . $node_left . ' AND ' . $node_right . ' '
            . 'THEN ' . $this->f_right . '+' . ($target_right - 1 - $node_right) . ' '
            . 'ELSE ' . $this->f_right . ' '
            . 'END '
            . 'WHERE (' . $this->f_left . ' BETWEEN ' . $node_left . ' AND ' . $target_right . ' '
            . 'OR ' . $this->f_right . ' BETWEEN ' . $node_left . ' AND ' . $target_right . ')';
        }

        $sql .= ' ' . $this->getCondition();

        $this->con->execute($sql);
    }

    /**
     * Sets the node position.
     *
     * @param   mixed   $nodeA      The node a
     * @param   mixed   $nodeB      The node b
     * @param   string  $position   The position
     *
     * @throws  Exception
     */
    public function setNodePosition(mixed $nodeA, mixed $nodeB, string $position = 'after'): void
    {
        $nodeA = (int) $nodeA;
        $nodeB = (int) $nodeB;

        $rs = $this->getChildren(0, $nodeA);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $A_left  = $rs->{$this->f_left};
        $A_right = $rs->{$this->f_right};
        $A_level = $rs->level;

        $rs = $this->getChildren(0, $nodeB);
        if ($rs->isEmpty()) {
            throw new DatabaseException('Node does not exist.');
        }
        $B_left  = $rs->{$this->f_left};
        $B_right = $rs->{$this->f_right};
        $B_level = $rs->level;

        if ($A_level != $B_level) {
            throw new DatabaseException('Cannot change position');
        }

        $rs      = $this->getParents($nodeA);
        $parentA = $rs->isEmpty() ? 0 : $rs->{$this->f_id};
        $rs      = $this->getParents($nodeB);
        $parentB = $rs->isEmpty() ? 0 : $rs->{$this->f_id};

        if ($parentA != $parentB) {
            throw new DatabaseException('Cannot change position');
        }

        if ($position == 'before') {
            if ($A_left > $B_left) {
                $sql = 'UPDATE ' . $this->table . ' SET '
                . $this->f_right . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_right . ' - (' . ($A_left - $B_left) . ') '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . $B_left . ' AND ' . ($A_left - 1) . ' THEN ' . $this->f_right . ' +  ' . ($A_right - $A_left + 1) . ' ELSE ' . $this->f_right . ' END, '
                . $this->f_left . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_left . ' - (' . ($A_left - $B_left) . ') '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . $B_left . ' AND ' . ($A_left - 1) . ' THEN ' . $this->f_left . ' + ' . ($A_right - $A_left + 1) . ' ELSE ' . $this->f_left . ' END '
                . 'WHERE ' . $this->f_left . ' BETWEEN ' . $B_left . ' AND ' . $A_right;
            } else {
                $sql = 'UPDATE ' . $this->table . ' SET '
                . $this->f_right . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_right . ' + ' . (($B_left - $A_left) - ($A_right - $A_left + 1)) . ' '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($A_right + 1) . ' AND ' . ($B_left - 1) . ' THEN ' . $this->f_right . ' - (' . (($A_right - $A_left + 1)) . ') ELSE ' . $this->f_right . ' END, '
                . $this->f_left . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_left . ' + ' . (($B_left - $A_left) - ($A_right - $A_left + 1)) . ' '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($A_right + 1) . ' AND ' . ($B_left - 1) . ' THEN ' . $this->f_left . ' - (' . ($A_right - $A_left + 1) . ') ELSE ' . $this->f_left . ' END '
                . 'WHERE ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . ($B_left - 1);
            }
        } else {
            if ($A_left > $B_left) {
                $sql = 'UPDATE ' . $this->table . ' SET '
                . $this->f_right . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_right . ' - (' . ($A_left - $B_left - ($B_right - $B_left + 1)) . ') '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($B_right + 1) . ' AND ' . ($A_left - 1) . ' THEN ' . $this->f_right . ' +  ' . ($A_right - $A_left + 1) . ' ELSE ' . $this->f_right . ' END, '
                . $this->f_left . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_left . ' - (' . ($A_left - $B_left - ($B_right - $B_left + 1)) . ') '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($B_right + 1) . ' AND ' . ($A_left - 1) . ' THEN ' . $this->f_left . ' + ' . ($A_right - $A_left + 1) . ' ELSE ' . $this->f_left . ' END '
                . 'WHERE ' . $this->f_left . ' BETWEEN ' . ($B_right + 1) . ' AND ' . $A_right;
            } else {
                $sql = 'UPDATE ' . $this->table . ' SET '
                . $this->f_right . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_right . ' + ' . ($B_right - $A_right) . ' '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($A_right + 1) . ' AND ' . $B_right . ' THEN ' . $this->f_right . ' - (' . (($A_right - $A_left + 1)) . ') ELSE ' . $this->f_right . ' END, '
                . $this->f_left . ' = CASE WHEN ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $A_right . ' THEN ' . $this->f_left . ' + ' . ($B_right - $A_right) . ' '
                . 'WHEN ' . $this->f_left . ' BETWEEN ' . ($A_right + 1) . ' AND ' . $B_right . ' THEN ' . $this->f_left . ' - (' . ($A_right - $A_left + 1) . ') ELSE ' . $this->f_left . ' END '
                . 'WHERE ' . $this->f_left . ' BETWEEN ' . $A_left . ' AND ' . $B_right;
            }
        }

        $sql .= $this->getCondition();
        $this->con->execute($sql);
    }

    /**
     * Gets the condition.
     *
     * @param   string  $start      The start
     * @param   string  $prefix     The prefix
     *
     * @return  string              The condition.
     */
    protected function getCondition(string $start = 'AND', string $prefix = ''): string
    {
        if (empty($this->add_condition)) {
            return '';
        }

        $w = [];
        foreach ($this->add_condition as $c => $n) {
            $w[] = $prefix . $c . ' = ' . $n;
        }

        return ' ' . $start . ' ' . implode(' AND ', $w) . ' ';
    }
}
