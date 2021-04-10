<?php

namespace SimpleMapper;


interface StatementInterface
{

    /**
     * Define cache options parameters,
     *
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     * @param array $options Depending of cache option
     * @return StatementInterface
     *
     * @example
     *  $stmt->useCache($redis, ['expire' => 60, 'prefix'=> 'customer_'])
     */
    public function useCache(\Psr\Cache\CacheItemPoolInterface $cache, $options = []): StatementInterface;

    /**
     * Hydrate the value defined in $from to element (object or scalar) $to.
     *
     * @param string|array $from The name of the source
     * @param mixed $to The output target car be a value define in filter_value or array or class name
     * @param string|null $keyName the array key name
     * @return StatementInterface
     *
     * Examples:
     *    $stmt->hydrate('i', 'int')
     *
     * here the array key name is not defined, It will be 'c'
     *    $stmt->hydrate('c', Customer::class)
     *
     * here the array key name is defined and will be 'theCustomer'
     *    $stmt->hydrate('c', Customer::class, 'theCustomer')
     *
     * will hydrate using several columns, The key name here is c1.
     *    $stmt->hydrate(['col1', 'col2', 'col3'], Customer::class)
     *
     * will hydrate using several columns, The key name here is c1.
     *    $stmt->hydrate(['col1', 'col2', 'col3'], Customer::class)
     *
     * will hydrate using several columns, The name here is cus
     *    $stmt->hydrate(['col1', 'col2', 'col3'], Customer::class, 'cus')
     */
    public function hydrate($from, string $to, string $keyName = null): StatementInterface;

    /**
     * Define parameter
     *
     * @param string $variableName
     * @param mixed $value Scalar
     * @param string|null $forcedType
     * @return StatementInterface
     *
     * @example
     *   $stmt->setParameter('age', 34)
     *
     * Here we force type. The type will be defined as is "::varchar"
     *   $stmt->setParameter('nameClient', 'Robert', 'varchar')
     */
    public function setParameter(string $variableName, $value, string $forcedType = null): StatementInterface;

    /**
     * Define bulk parameters
     * @param array $parameters
     * @return StatementInterface
     *
     * @example
     *  $stmt->setParameters(['age' => 34, ['name' => 'nameClient', 'value' => 'Robert', 'type' => 'varchar']]);
     */
    public function setParameters(array $parameters): StatementInterface;

    /**
     * Extract values from an object or an array using the name.
     *
     * @param object $source Object
     * @param array $variableNames list of variables. If null, all variables will be searched
     * @param string $prefix    Prefix used on the SQL (not in the source)
     * @return StatementInterface
     *
     * @example
     *
     * For all placeholder, it will search value from variable name from the object
     *   ->extractParameterFrom($object)
     *
     * For each placeholder in the array, it will search value from variable name from the object
     *   ->extractParameterFrom($object, ['name'])
     *
     * For each placeholder in the array (* == all), it will search value from variable name that started with cus_ from the object
     *   ->extractParameterFrom($object, ['*'], 'cus_')
     */
    public function extractParametersFrom(object $source, array $variableNames = null, string $prefix = null): StatementInterface;

    /**
     * Will execute the query
     */
    public function execute(): void;

    /**
     * Fetch values using a cursor. The final output will be always an array.
     */
    public function fetch();

    /**
     * Fetch all values
     * @return array
     */
    public function fetchAll($fetchStyle = \PDO::FETCH_ASSOC): array;

    /**
     * Fetch only one column. The output will be typed depending of the item wanted.
     * @param string|array $column Can be the name of the column or the index
     *
     * @example
     *
     * will return the first column
     *      $stm->fetchColumn();
     *
     * will return the first column
     *      $stm->fetchColumn(0);
     *
     * will return the second column
     *      $stm->fetchColumn(1);
     *
     * will return the column with alias "c"
     *      $stm->fetchColumn('c');
     */
    public function fetchColumn($column = 0);

    /**
     * Will return the SQL.
     * First row will be the SQL query with standard placeholders.
     * The second row will be the scalar parameter values.
     *
     * @return array
     */
    public function getQuery(): array;
}