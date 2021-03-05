<?php

namespace SimpleMapper;

use SimpleMapper\Exception\StatementExecutionNotRanException;
use SimpleMapper\Exception\PostgresError;
use SimpleMapper\Exception\PlaceholderExpectedException;
use Symfony\Component\Serializer\Serializer;

final class Statement implements StatementInterface, \Countable
{
    /**
     * @var Dbal
     */
    private $dbal;

    /**
     * @var string
     */
    private $rawSql;

    /**
     * @var string
     */
    private $compiledSql;

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @var array
     */
    private $objectParameters = [];

    /**
     * @var array
     */
    private $compiledParameters = [];

    /**
     * @var string
     */
    private $queryName;

    /**
     * @var \PDOStatement
     */
    private $statement;
    /**
     * @var array
     */
    private $options;

    /**
     * @var array
     */
    private $hydrateParameters = [];

    private const SCALAR_TYPES = ['int', 'integer', 'string', 'bool', 'boolean', 'float', 'double'];
    private const ACCEPTED_TYPES = self::SCALAR_TYPES + ['json', 'date', 'datetime'];
    /**
     * @var Serializer
     */
    private $serializer;
    private $cache;
    private $cacheOptions;
    private $itWasExecuted = false;

    const YOU_SHOULD_RUN_EXECUTE_METHOD_FIRST = 'You should run execute() method first.';

    public function __construct(Dbal $dbal, string $sql, array $options = null, Serializer $serializer = null)
    {
        $this->dbal = $dbal;
        $this->rawSql = $sql;
        $this->options = $options;
        $this->serializer = $serializer;
    }

    /**
     * @inheritdoc
     */
    public function useCache(\Psr\Cache\CacheItemPoolInterface $cache, $options = []): StatementInterface
    {
        $this->cache = $cache;
        $this->cacheOptions = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($from, string $to, string $keyName = null): StatementInterface
    {
        $this->initialize();

        $acceptedType = self::ACCEPTED_TYPES;
        if (!is_string($from) && !is_array($from)) {
            throw new \InvalidArgumentException(sprintf("The first argument of %s method should be type of string or array. %s given.", __METHOD__, gettype($from)));
        }

        if (!in_array($to, $acceptedType) && !class_exists($to)) {
            throw new \InvalidArgumentException(sprintf("The second argument of %s method is not valid. It should be an existing class path or one value from : %.", __METHOD__, implode(', ', $acceptedType)));
        }

        $name = $keyName ?? is_string($from) ? $from : 'c' . (count($this->hydrateParameters) + 1);
        $this->hydrateParameters[] = [
            'from' => $from,
            'to' => $to,
            'keyname' => $name
        ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setParameter(string $variableName, $value, string $forcedType = null): StatementInterface
    {
        $this->initialize();

        if (!is_scalar($value) && !is_null($value)) {
            new \InvalidArgumentException(sprintf("The parameter \"%s\" is expected to be null or scalar, but currently \"%s\" sent.", $variableName, gettype($value)));
        }
        $this->parameters[$variableName] = ['value' => $value, 'type' => $forcedType];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setParameters(array $parameters): StatementInterface
    {
        $this->initialize();

        foreach ($parameters as $variableName => $parameter) {
            if (!is_string($variableName) && (!is_array($parameter) || count($parameter) <= 1 || count($parameter) > 3)) {
                throw new \InvalidArgumentException("Parameters are not valid.");
            }

            if (is_string($variableName)) {
                $this->setParameter($variableName, $parameter);
            } else {
                $variableName = $parameter['name'];
                $value = $parameter['value'];
                $type = $parameter['type'] ?? null;

                $this->setParameter($variableName, $value, $type);
            }

        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function extractParametersFrom(object $source, array $variableNames = null, string $prefix = null): StatementInterface
    {
        $this->initialize();

        $this->objectParameters[] = [
            'source' => $source,
            'variables' => $variableNames,
            'prefix' => $prefix
        ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function execute(): void
    {
        $this->compile();
        $sql = $this->compiledSql;
        $this->queryName = $queryName = uniqid('thequery');

        if ($this->hydrateParameters) {
            $forExecutionSql = <<<SQL
WITH $queryName as (
  $sql
)
SELECT ROW_TO_JSON(q) FROM $queryName q;
SQL;
        } else {
            $forExecutionSql = $sql;
        }

        $this->statement = $this->dbal->getConnection()->prepare($forExecutionSql);
        $this->statement->execute($this->compiledParameters);

        if ($this->statement->errorCode() != 0) {
            throw new PostgresError($this->statement->errorInfo()[2]);
        }

        $this->itWasExecuted = true;
    }

    /**
     * @inheritdoc
     */
    public function fetch()
    {
        if (!$this->itWasExecuted) {
            throw new StatementExecutionNotRanException(self::YOU_SHOULD_RUN_EXECUTE_METHOD_FIRST);
        }

        //TODO Add cache
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new PostgresError($this->statement->errorInfo());
        }

        return $this->extractValueFromRow($row);
    }

    /**
     * @inheritdoc
     */
    public function fetchAll(): array
    {
        if (!$this->itWasExecuted) {
            throw new StatementExecutionNotRanException(self::YOU_SHOULD_RUN_EXECUTE_METHOD_FIRST);
        }

        //TODO Add cache
        $result = [];

        $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            throw new PostgresError($this->statement->errorInfo());
        }

        foreach ($rows as $row) {
            $result[] = $this->extractValueFromRow($row);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($column = 0)
    {
        if (!$this->itWasExecuted) {
            throw new StatementExecutionNotRanException(self::YOU_SHOULD_RUN_EXECUTE_METHOD_FIRST);
        }

        //TODO review that
        //TODO Add cache

        $row = $this->statement->fetch();

        if ($row === false) {
            $errorInfo = $this->statement->errorInfo();
            if ($errorInfo[0] == "00000") {
                return null;
            }
            throw new PostgresError(json_encode($errorInfo, 128));
        }

        $row = $this->extractValueFromRow($row);

        if (is_int($column)) {
            $row = array_values($row);
        } elseif (!is_string($column)) {
            throw new \InvalidArgumentException("Column should be an integer or a string.");
        }

        if (!isset($row[$column])) {
            throw new \InvalidArgumentException("Column \"$column\" was not found.");
        }

        return $row[$column];
    }

    private function compile()
    {
        $sql = $this->rawSql;
        $regexp = '(\$\(\s*[a-zA-Z][0-9a-zA-Z\_]+\s*\))';

        preg_match_all($regexp, $sql, $matches);

        $match = $matches[0];


        $variables = [];
        foreach ($match as $item) {
            $variable = trim($item, " \t\n\r\0\x0B\$\(\)");
            $variables[$item] = $variable;
        }

        $this->extractParametersFromObjects($variables);

        $this->compiledParameters = [];

        foreach ($variables as $placeholder => $key) {
            if (!array_key_exists($key, $this->parameters)) {
                throw new PlaceholderExpectedException("The parameter \"$key\" was not found in parameter list.");
            }

            $cast = isset($this->parameters[$key]['type']) ? '::' . $this->parameters[$key]['type'] : null;

            $sql = str_replace($placeholder, ':' . $key . $cast, $sql);

            $this->compiledParameters[$key] = $this->parameters[$key]['value'];
        }

        $this->compiledSql = $sql;
    }

    /**
     * @inheritdoc
     */
    public function getQuery(): array
    {
        $this->compile();

        return [
            'sql' => $this->compiledSql,
            'parameters' => $this->compiledParameters
        ];
    }

    private function extractParametersFromObjects(array $variables)
    {
        foreach ($this->objectParameters as $objectParameter) {

            $source = $objectParameter['source'];
            $prefix = $objectParameter['prefix'];

            $selectedVariables = [];
            foreach ((array)$objectParameter['variables'] as $variable) {
                $selectedVariables[] = $prefix . $variable;
            }

            $refClass = new \ReflectionClass($source);

            $refProperties = $refClass->getProperties();

            foreach ($refProperties as $item) {
                $item->setAccessible(true);
                $rawValue = $item->getValue($source);

                if (is_object($rawValue) && method_exists($rawValue, '__toString')) {
                    $value = (string)$rawValue;
                } elseif (is_scalar($rawValue) || is_null($rawValue)) {
                    $value = $rawValue;
                } elseif ($rawValue instanceof \DateTimeInterface) {
                    $value = $rawValue->format(DATE_ISO8601);
                } else {
                    continue;
                }

                $searchedName = $prefix . $item->getName();

                if ($selectedVariables && !in_array($searchedName, $selectedVariables)) {
                    continue;
                }

                if (!in_array($searchedName, $variables)) {
                    continue;
                }

                $this->setParameter($searchedName, $value);
            }
        }
    }

    /**
     * @param $row
     * @return array
     * @throws \Exception
     */
    private function extractValueFromRow($row): array
    {
        if (isset($row['row_to_json']) && ($r = json_decode($row['row_to_json'], true)) !== false) {
            $row = $r;
        }

        if (count($this->hydrateParameters) == 0) {
            return $row;
        }

        $finalResults = [];
        foreach ($this->hydrateParameters as $parameter) {
            $from = $parameter['from'];
            $to = $parameter['to'];
            $name = $parameter['keyname'];
            if (is_string($from)) {
                if (in_array($to, self::ACCEPTED_TYPES)) {
                    switch ($to) {
                        case 'int':
                        case 'integer':
                            $result = (int)$row[$from];
                            break;
                        case 'bool':
                        case 'boolean':
                            $result = (bool)$row[$from];
                            break;
                        case 'float':
                        case 'double':
                            $result = (double)$row[$from];
                            break;
                        case 'string':
                            $result = (string)$row[$from];
                            break;
                        case 'date':
                        case 'datetime':
                            $result = new \DateTimeImmutable($row[$from]);
                            break;
                        case 'json':
                            $result = json_decode($row[$from]);
                            break;
                    }
                } else {
                    $result = $this->serializer->denormalize($row[$from], $to);
                }
            } elseif (is_array($from)) {
                $froms = $from;
                if (in_array($to, self::ACCEPTED_TYPES)) {
                    foreach ($froms as $from) {
                        switch ($to) {
                            case 'int':
                            case 'integer':
                                $result = (int)$row[$from];
                                break;
                            case 'bool':
                            case 'boolean':
                                $result = (bool)$row[$from];
                                break;
                            case 'float':
                            case 'double':
                                $result = (double)$row[$from];
                                break;
                            case 'string':
                                $result = (string)$row[$from];
                                break;
                            case 'date':
                            case 'datetime':
                                $result = new \DateTimeImmutable($row[$from]);
                                break;
                            case 'json':
                                $result = json_decode($row[$from]);
                                break;
                        }
                    }
                } else {
                    $values = array_intersect_key($row, array_flip($froms));

                    $result = $this->serializer->denormalize($values, $to);
                }
            }
            $finalResults[$name] = $result;
        }

        return $finalResults;
    }

    public function getStatement(): ?\PDOStatement
    {
        return $this->statement;
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        if (!$this->itWasExecuted) {
            throw new StatementExecutionNotRanException(self::YOU_SHOULD_RUN_EXECUTE_METHOD_FIRST);
        }

        return $this->statement->rowCount();
    }

    private function initialize(): void
    {
        $this->itWasExecuted = false;
        $this->statement = null;
    }
}