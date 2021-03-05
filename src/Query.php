<?php

namespace SimpleMapper;

use SimpleMapper\Serializer\Denormalizer\DateTimeRangeDenormalizer;
use SimpleMapper\Serializer\Normalizer\DateTimeRangeNormalizer;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Query
{
    const FETCH_ONE_ELEMENT = '5e52e16f-1749-4010-82ff-93b183e48dc0';
    const FETCH_ALL         = '111ffaab-1d20-4c99-ae32-107d681262ac';

    private $query;

    private $hydrators = [];
    /**
     * @var Dbal
     */
    private $connection;
    /**
     * @var array
     */
    private $parameters;

    public function __construct(Dbal $connection, string $query, array $parameters = [])
    {
        $this->query      = $query;
        $this->connection = $connection;
        $this->parameters = $parameters;

        $this->serializer = new Serializer([
            new DateTimeRangeNormalizer(),
            new DateTimeRangeDenormalizer(),
            new DateTimeNormalizer(),
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
        ],
            [new JsonEncoder()]);;
    }

    public function addHydrator(string $alias, string $format): Query
    {
        $availableFormat = ['scalar', 'array', 'datetime'];
        if (!class_exists($format) && !in_array($format, $availableFormat)) {
            throw new \InvalidArgumentException(sprintf('Format "%s" is not valid. The format should be a valid class or one of those values : %s.', $format, implode(', ', $availableFormat)));
        }

        $this->hydrators[$alias] = $format;

        return $this;
    }

    public function addParameter(string $key, $value): Query
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    public function setParameters(array $parameters): Query
    {
        //TODO check keys
        $this->parameters = $parameters;

        return $this;
    }

    public function execute(string $fetchMode = Dbal::FETCH_ALL)
    {
        $queryName = uniqid('thequery');
        $sql       = $this->query;

        $query = <<<SQL
WITH $queryName as (
  $sql
)
SELECT ROW_TO_JSON(q) FROM $queryName q;
SQL;

        $statement = $this->connection->prepare($query);
        $statement->execute($this->parameters);
        $finalResult = [];

        while ($row = $statement->fetch(\PDO::FETCH_COLUMN)) {
            $values      = json_decode($row, true);
            $currentLine = [];
            foreach ($this->hydrators as $alias => $format) {

                if (!array_key_exists($alias, $values)) {
                    throw new \InvalidArgumentException("Alias \"$alias\" doesn't exists in the row set.");
                }

                if ($values[$alias] === null) {
                    $currentLine[$alias] = null;
                } else {
                    switch ($format) {
                        case 'datetime':
                            $currentLine[$alias] = new \DateTime($values[$alias]);
                            break;
                        case 'scalar':
                            if (!is_scalar($values[$alias])) {
                                throw new \InvalidArgumentException(sprintf("Current value is not scalar but %s.", gettype($values[$alias])));
                            }
                            $currentLine[$alias] = $values[$alias];
                            break;
                        case 'array' :
                            $currentLine[$alias] = (array)$values[$alias];
                            break;
                        default:
                            $currentLine[$alias] = $this->serializer->deserialize(json_encode($values[$alias]), $format, 'json');
                    }
                }

                if ($fetchMode == self::FETCH_ONE_ELEMENT) {
                    return $currentLine[$alias];
                }
            }

            $finalResult[] = $currentLine;
        }

        return $finalResult;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getHydrators(): array
    {
        return $this->hydrators;
    }
}