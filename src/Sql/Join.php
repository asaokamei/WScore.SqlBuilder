<?php

namespace WScore\ScoreSql\Sql;

use InvalidArgumentException;
use WScore\ScoreSql\Builder\Bind;
use WScore\ScoreSql\Builder\Quote;

class Join implements JoinInterface
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $alias;

    /**
     * @var string
     */
    protected $type = 'JOIN';

    /**
     * @var string[]
     */
    protected $usingKey = [];

    /**
     * @var string|Where
     */
    protected $criteria;

    /**
     * @var Bind
     */
    protected $bind;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var string
     */
    protected $queryTable;

    // +----------------------------------------------------------------------+
    //  managing objects.
    // +----------------------------------------------------------------------+
    /**
     * @param string $table
     * @param string|null $alias
     */
    public function __construct($table, $alias = null)
    {
        $this->table = $table;
        $this->alias = $alias;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return JoinInterface
     */
    public static function table($table, $alias = null)
    {
        return new self($table, $alias);
    }

    /**
     * @return JoinInterface
     */
    public function left()
    {
        $this->by('LEFT OUTER JOIN');
        return $this;
    }

    /**
     * @param string $type
     * @return JoinInterface
     */
    public function by($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return JoinInterface
     */
    public function right()
    {
        $this->by('RIGHT OUTER JOIN');
        return $this;
    }

    /**
     * for setting parent query's table or alias name.
     * will be used in Sql::join method.
     *
     * @param string $queryTable
     * @return $this
     */
    public function setQueryTable($queryTable)
    {
        $this->queryTable = $queryTable;
        return $this;
    }

    /**
     * @param string|array|callable $key
     * @return JoinInterface
     */
    public function using($key)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
        } elseif (is_array($key)) {
            $args = $key;
        } else {
            $args = [$key];
        }
        $this->usingKey = $args;
        return $this;
    }

    /**
     * @param Where|string $criteria
     * @return JoinInterface
     */
    public function on($criteria)
    {
        $this->criteria = $criteria;
        return $this;
    }

    // +----------------------------------------------------------------------+
    //  build sql statement.
    // +----------------------------------------------------------------------+
    /**
     * @param Bind|null $bind
     * @param Quote|null $quote
     * @return string
     */
    public function build($bind = null, $quote = null)
    {
        $this->bind = $bind;
        $this->quote = $quote;
        $join = [
            $this->buildJoinType(),
            $this->buildTable(),
            $this->buildUsingOrOn(),
        ];
        return implode(' ', $join);
    }

    /**
     * @return string
     */
    protected function buildJoinType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    protected function buildTable()
    {
        $table = $this->quote($this->table);
        if ($this->alias) {
            $table .= ' ' . $this->quote($this->alias);
        }
        return $table;
    }

    /**
     * @param string|array $name
     * @return string|array
     */
    protected function quote($name)
    {
        if (!$this->quote) {
            return $name;
        }
        if (is_array($name)) {
            return $this->quote->map($name);
        }
        return $this->quote->quote($name);
    }

    /**
     * @return string
     */
    protected function buildUsingOrOn()
    {
        if ($this->criteria) {
            return $this->buildOn();
        }
        if ($this->usingKey) {
            return $this->buildUsing();
        }
        return '';
    }

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    protected function buildOn()
    {
        if (is_object($this->criteria) && $this->criteria instanceof Where) {
            $sql = $this->criteria->build($this->bind, $this->quote, $this->alias, $this->queryTable);
        } elseif (is_string($this->criteria)) {
            $sql = $this->criteria;
        } else {
            throw new InvalidArgumentException;
        }
        if (!empty($this->usingKey)) {
            $using = [];
            foreach ($this->usingKey as $item) {
                $using[] = $this->quote($this->alias() . '.' . $item) .
                    '=' .
                    $this->quote($this->queryTable . '.' . $item);
            }
            $sql = implode(' AND ', $using) .
                ' AND ( ' . $sql . ' )';
        }
        return 'ON ( ' . $sql . ' )';
    }

    /**
     * @return string
     */
    protected function alias()
    {
        return $this->alias ?: $this->table;
    }

    /**
     * @return string
     */
    protected function buildUsing()
    {
        $using = [];
        foreach ($this->usingKey as $item) {
            $using[] = $this->quote($item);
        }
        return 'USING( ' . implode(', ', $using) . ' )';
    }
}