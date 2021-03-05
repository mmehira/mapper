# Simple Mapper for PostgreSQL

# /!\ IT'S JUST A POC. DON'T USE IN PRODUCTION

## Installation 

```bash
composer require "mehira/simple-mapper"
```

## Example

```php
<?php

include __DIR__.'/vendor/autoload.php';

$sql = "select c from customer c WHERE id = $( id);";

$serializer = new Serializer(); // ...

$dbal = new \SimpleMapper\Dbal($serializer, 'pgsql:host=localhost;port=5432;dbname=testdb;user=theuser;password=secretPass');

$stmt = $dbal->prepare($sql);

$stmt->hydrate('c', Customer::class)
    ->setParameter('id', 1)
    ->execute();

$customer = $stmt->fetchColumn();
```

## Full example

```php
<?php
// ...
$dbal = new \SimpleMapper\Dbal($serializer, 'pgsql:host=localhost;port=5432;dbname=testdb;user=theuser;password=secretPass');

$stm = $dbal->prepare("select 12 as i, c from custormer c where name ILIKE $(nameClient) ");

$stm->hydrate('i', 'int')
    ->hydrate('b', 'bool')
    ->hydrate('c', Customer::class) // here the array key name is not defined, It will be 'c'
    ->hydrate('c', Customer::class, 'theCustomer') // here the array key name is defined and will be 'theCustomer'
    ->hydrate(['col1', 'col2', 'col3'], Customer::class) // will hydrate using several columns, The key name here is c1.
    ->hydrate(['col1', 'col2', 'col3'], Customer::class) // will hydrate using several columns, The key name here is c1.
    ->hydrate(['col1', 'col2', 'col3'], Customer::class, 'cus') // will hydrate using several columns, The name here is cus

    ->setParameter('id', 123)
    ->setParameter('nameClient', 'test', 'varchar') // we can force the type. In this case the final value will be 'test'::varchar

    ->setParameters(['id' => 123, ['name' => 'ref', 'value' => 'ABC', 'type' => 'varchar']])

    ->extractParametersFrom($object) // for each placeholder, it will search value from variable name from the object
    ->extractParametersFrom($object, ['name']) // for each placeholder in the array, it will search value from variable name from the object
    ->extractParametersFrom($object, ['name'], 'cus_') // for each placeholder in the array, it will search value from variable name that started with cus_ from the object

    ->execute();

$rows = $stm->fetch(); // will return a generator
$rows = $stm->fetchAll(); // will return all lines as array assoc
$rows = $stm->fetchColumn(); // will return the first column
$rows = $stm->fetchColumn(0); // will return the first column
$rows = $stm->fetchColumn(1); // will return the second column
$rows = $stm->fetchColumn('c'); // will return the second column

$sql = $stm->getQuery(); // will return the final sql query with array of parameters
```

## Transaction example:

```php
<?php 
// ...
    $dbal = new \SimpleMapper\Dbal($serializer, 'pgsql:host=localhost;port=5432;dbname=testdb;user=theuser;password=secretPass');
    
    $dbal->beginTransaction();
    $stmt = $dbal->prepare("INSERT INTO customer VALUES (99, 'Alain', NOW())");
    $stmt->execute();
    
    $dbal->addSavePoint('beforeUpdate');
    
    $stmt = $dbal->prepare("UPDATE customer SET name = 'bla bla' WHERE id = 1");
    $stmt->execute();
    
    $stmt = $dbal->prepare("select name from customer WHERE id = 1");
    $stmt->execute();
    $name = $stmt->fetchColumn();
    
    assert('bla bla' == $name);
    
    $dbal->rollBack('beforeUpdate');
    
    $stmt = $dbal->prepare("select name from customer WHERE id = 1");
    $stmt->execute();
    $name = $stmt->fetchColumn();
    
    assert('Albert' == $name);
    
    $dbal->commit();
    
    $stmt = $dbal->prepare("select name from customer WHERE id = 99");
    $stmt->execute();
    $name = $stmt->fetchColumn();
    
    assert('Alain' == $name);

``` 

## Integration to Symfony

Simple-Mapper has a depency to Symfony Serializer.

* First, add it to your Symfony project:

```bash
composer require serializer
```

* Create Environment variables on `.env` file:

```bash
DATABASE_DSN=pgsql:host=localhost;port=5432;dbname=testdb;user=theuser;password=secretPass
```

* Create a new service on `services.yml`: 
```yaml
    SimpleMapper\Dbal:
        arguments:
            $dsn: "%env(DATABASE_DSN)%"
```

You can create a repository for a specific table:

```php
<?php

namespace App\Repository\Communication;

use SimpleMapper\Repository\AbstractRepository;

class CustomerRepository extends AbstractRepository
{
    protected function getBaseClass(): string
    {
        return Customer::class;
    }

    protected function getTableName(): string
    {
        return "sales.customer";
    }
}
```

The repository is ready to use in your services:

```php
<?php
// ...
    /**
     * @param CustomerRepository $repository
     * @return Customer[]
     */
    public function getAllCustomers(CustomerRepository $repository): array
    {    
        return $repository->findAll();
    }

    /**
     * @param CustomerRepository $repository
     * @return Customer[]
     */
    public function getFrenchCustomers(CustomerRepository $repository): array
    {    
        return $repository->find(['country' => 'FR']);
    }

    /**
     * @param CustomerRepository $repository
     * @return Customer
     */
    public function getOneCustomer(CustomerRepository $repository): Customer
    {    
        return $repository->findFirst(['is_master' => true]);
    }

```