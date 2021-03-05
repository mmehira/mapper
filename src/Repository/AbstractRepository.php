<?php

namespace SimpleMapper\Repository;

use SimpleMapper\Dbal;

abstract class AbstractRepository
{
    /**
     * @var Dbal
     */
    protected $dbal;

    final public function __construct(Dbal $dbal)
    {
        $this->dbal = $dbal;
    }

    abstract protected function getBaseClass(): string;

    abstract protected function getTableName(): string;

    public function findAll()
    {
        $sql = sprintf("select t from %s t", $this->getTableName());

        $stmt = $this->dbal->prepare($sql);
        $stmt->hydrate('t', $this->getBaseClass());
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetchColumn('t')) {
            $result[] = $row;
        }

        return $result;
    }

    public function find(array $parameters = [])
    {
        if (!$parameters) {
            return $this->findAll();
        }

        $sql = sprintf("select t from %s t ", $this->getTableName());

        $where = [];
        foreach ($parameters as $name => $parameter) {
            $where[] = sprintf('%s = $(%s)', $name, $name);
        }

        $sql = $sql . 'WHERE '.implode(' AND ', $where);

        $stmt = $this->dbal->prepare($sql);
        $stmt->hydrate('t', $this->getBaseClass());
        $stmt->setParameters($parameters);

        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetchColumn('t')) {
            $result[] = $row;
        }

        return $result;
    }

    public function findFirst(array $parameters = [])
    {
        $sql = sprintf("select t from %s t ", $this->getTableName());

        $where = [];
        foreach ($parameters as $name => $parameter) {
            $where[] = sprintf('%s = $(%s)', $name, $name);
        }

        if ($where) {
            $filter = 'WHERE '.implode(' AND ', $where);
        } else {
            $filter = '';
        }
        $sql = $sql . $filter . ' LIMIT 1' ;

        $stmt = $this->dbal->prepare($sql);
        $stmt->hydrate('t', $this->getBaseClass());
        $stmt->setParameters($parameters);

        $stmt->execute();

        return $stmt->fetchColumn('t');
    }
}