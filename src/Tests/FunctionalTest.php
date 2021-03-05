<?php

namespace SimpleMapper\Tests;

use SimpleMapper\Dbal;
use SimpleMapper\Exception\StatementExecutionNotRanException;
use SimpleMapper\Serializer\Denormalizer\Type\Range\DateTimeRangeDenormalizer;
use SimpleMapper\Serializer\Denormalizer\EnumDenormalizer;
use SimpleMapper\Serializer\Normalizer\DateTimeRangeNormalizer;
use SimpleMapper\Serializer\Normalizer\EnumNormalizer;
use SimpleMapper\Tests\Model\Customer;
use SimpleMapper\Tests\Model\Customer2;
use SimpleMapper\Type\Enum\Day;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class FunctionalTest extends TestCase
{

    private function getSerializer(){
        return new Serializer([
            new DateTimeRangeNormalizer(),
            new DateTimeRangeDenormalizer(),
            new EnumNormalizer(),
            new EnumDenormalizer(),
            new DateTimeNormalizer(),
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
        ],
            [new JsonEncoder()]);;
    }
    protected function setUp()
    {
        parent::setUp();

        $dsn = $this->getDsn();
        $pdo = new \PDO($dsn);

        $sql = [];

        $sql[] = "DROP TABLE IF EXISTS customer;";
        $sql[] = <<<SQL
CREATE TABLE customer (
    id SERIAL PRIMARY KEY ,
    name VARCHAR,
    created_at TIMESTAMP WITH TIME ZONE,
    b int DEFAULT 3
);
SQL;
        $sql[] = "insert INTO customer (id, name, created_at) VALUES (1,'Albert','2018-01-01 10:10:10'),(2,'Louis', '2018-02-01 20:20:20'),(3,'Mohammed', '2018-03-03 03:30:30');";


        foreach ($sql as $s) {
            $pdo->exec($s);
        }
    }

    public function testCanExtractOnlyOneVariableAndHydrateObject()
    {

        $sql = "select c from customer c WHERE id = $( id);";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->hydrate('c', Customer::class)
            ->setParameter('id', 1)
            ->execute();

        $result = $stmt->fetch();

        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('c', $result);

        $customerFetched = $result['c'];

        $this->assertInstanceOf(Customer::class, $customerFetched);

        $date = $customerFetched->created_at;

        $this->assertInstanceOf(\DateTimeInterface::class, $date);

        $customerExpected = new Customer();
        $customerExpected->id = 1;
        $customerExpected->name = 'Albert';
        $customerExpected->created_at = $date;

        $this->assertEquals($customerExpected, $customerFetched);
    }

    public function testCanExecuteQueryAndGetCount()
    {
        $sql = "select c from customer c";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        // With hydrate
        $stmt->hydrate('c', Customer::class)
            ->execute();
        $this->assertCount(3, $stmt);

        // Without hydrate
        $stmt->execute();
        $this->assertCount(3, $stmt);
    }

    public function testCanGetSqlQuery()
    {
        $sql = "select c from customer c where name = $(name1) AND name = $(     name1) AND id = $(The_iD_123) ;;;;;";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        // With hydrate
        $query = $stmt->hydrate('c', Customer::class)
            ->setParameter('name1', 'my name')
            ->setParameter('The_iD_123', '123')
            ->getQuery()
        ;
        $this->assertEquals($query['sql'], 'select c from customer c where name = :name1 AND name = :name1 AND id = :The_iD_123');
        $this->assertEquals($query['parameters'], ['name1' => 'my name', 'The_iD_123' => '123']);

        // Without hydrate
        $query = $stmt
            ->setParameter('name1', 'my name')
            ->setParameter('The_iD_123', '123')
            ->getQuery()
        ;
        $this->assertEquals($query['sql'], 'select c from customer c where name = :name1 AND name = :name1 AND id = :The_iD_123');
        $this->assertEquals($query['parameters'], ['name1' => 'my name', 'The_iD_123' => '123']);
    }

    public function testCanDefineParameters()
    {

        $sql = "select c from customer c WHERE id = $( id) and reference=$(ref);";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->hydrate('c', Customer::class);
        $stmt->setParameters(['id' => 123, ['name' => 'ref', 'value' => 'ABC', 'type' => 'varchar']]);

        $query = $stmt->getQuery();

        $this->assertEquals("select c from customer c WHERE id = :id and reference=:ref::varchar", $query['sql']);
        $this->assertEquals(['id' => 123, 'ref' => 'ABC'], $query['parameters']);
    }

    public function testExecutionException()
    {
        $sql = "select c from customer c";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $exceptionCount = 0;

        try {
            $stmt->count();
        } catch (StatementExecutionNotRanException $e) {
            $exceptionCount++;
        }

        $stmt->execute();
        $stmt->fetchColumn();
        $stmt->setParameter('aa', 123);

        try {
            $stmt->fetchColumn();
        } catch (StatementExecutionNotRanException $e) {
            $exceptionCount++;
        }

        $stmt->execute();
        $stmt->fetch();
        $stmt->setParameters(['aa' => 123]);

        try {
            $stmt->fetch();
        } catch (StatementExecutionNotRanException $e) {
            $exceptionCount++;
        }

        $stmt->execute();
        $stmt->fetch();

        $obj = new Customer();
        $obj->name = 'Mohammed';

        $stmt->extractParametersFrom($obj);

        try {
            $stmt->fetchAll();
        } catch (StatementExecutionNotRanException $e) {
            $exceptionCount++;
        }

        $this->assertEquals(4, $exceptionCount);
    }

    public function testCanExtractOnlyOneVariableAndHydrateObjectWithParameter()
    {

        $sql = "select c from customer c WHERE id = $( id);";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->hydrate('c', Customer::class)
            ->setParameter('id', 1)
            ->execute();

        $customerFetched = $stmt->fetchColumn();

        $date = $customerFetched->created_at;

        $this->assertInstanceOf(\DateTimeInterface::class, $date);

        $customerExpected = new Customer();
        $customerExpected->id = 1;
        $customerExpected->name = 'Albert';
        $customerExpected->created_at = $date;

        $this->assertEquals($customerExpected, $customerFetched);
    }


    public function testCanExtractOnlyOneVariableAndHydrateObjectWithParameterWithColmnName()
    {

        $sql = "select c, 123 as b from customer c;";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->hydrate('c', Customer::class)
            ->hydrate('b', 'int')
            ->execute();

        $customerFetched = $stmt->fetchColumn('c');

        $date = $customerFetched->created_at;

        $this->assertInstanceOf(\DateTimeInterface::class, $date);

        $customerExpected = new Customer();
        $customerExpected->id = 1;
        $customerExpected->name = 'Albert';
        $customerExpected->created_at = $date;

        $this->assertEquals($customerExpected, $customerFetched);

        $int = $stmt->fetchColumn('b'); // second line
        $this->assertEquals($int, 123);
    }

    public function testCanExtractTwoVariableAndHydrateObjectAndScalar()
    {
        $sql = "select c, 12 as r from customer c;";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->hydrate('c', Customer::class)
            ->hydrate('r', 'int')
            ->execute();

        $customersFetched = $stmt->fetchAll();

        $lines = [
            ['id' => 1, 'name' => 'Albert', 'date' => '2018-01-01 10:10:10'],
            ['id' => 2, 'name' => 'Louis', 'date' => '2018-02-01 20:20:20'],
            ['id' => 3, 'name' => 'Mohammed', 'date' => '2018-03-03 03:30:30']
        ];
        $idx = 0;
        foreach ($customersFetched as $item) {
            $this->assertArrayHasKey('c', $item);
            $this->assertArrayHasKey('r', $item);
            $object = $item['c'];
            $this->assertInstanceOf(Customer::class, $object);
            $this->assertEquals($lines[$idx]['id'], $object->id);
            $this->assertEquals($lines[$idx]['name'], $object->name);
            $this->assertEquals(new \DateTimeImmutable($lines[$idx]['date']), $object->created_at);
            $idx++;
        }
    }

    public function testCanExtractTwoVariablesAndHydrateObject()
    {
        $sql = "select name, id from customer c;";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->hydrate(['id', 'name'], Customer::class)
            ->execute();

        $customersFetched = $stmt->fetchAll();

        $lines = [
            ['id' => 1, 'name' => 'Albert'],
            ['id' => 2, 'name' => 'Louis'],
            ['id' => 3, 'name' => 'Mohammed']
        ];
        $idx = 0;
        foreach ($customersFetched as $item) {
            $this->assertArrayHasKey('c1', $item);
            $object = $item['c1'];
            $this->assertInstanceOf(Customer::class, $object);
            $this->assertEquals($lines[$idx]['id'], $object->id);
            $this->assertEquals($lines[$idx]['name'], $object->name);
            $this->assertNull($object->created_at);
            $idx++;
        }
    }

    public function testCanExtractParameterFromObject()
    {
        $sql = "select c from customer c where name = $(name);";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $obj = new Customer();
        $obj->name = 'Mohammed';

        $stmt->hydrate('c', Customer::class)
            ->extractParametersFrom($obj)
            ->execute();

        $customerFetched = $stmt->fetchColumn();

        $customerExpected = new Customer();
        $customerExpected->id = 3;
        $customerExpected->name = 'Mohammed';
        $customerExpected->created_at = new \DateTimeImmutable('2018-03-03 03:30:30');

        $this->assertEquals($customerExpected, $customerFetched);
    }

    public function testCanExtractVariableFrom2Objects()
    {
        $sql = "select $( first_name) as name1, $( last_name) as name2;;;;;";

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $obj1 = new Customer();
        $obj1->name = 'Albert';

        $obj2 = new Customer();
        $obj2->name = 'Mohammed';

        $stmt->extractParametersFrom($obj1, ['name'], 'first_')
            ->extractParametersFrom($obj2, ['name'], 'last_')
            ->execute();

        $result = $stmt->fetch();

        $this->assertArrayHasKey('name1', $result);
        $this->assertEquals('Albert', $result['name1']);
        $this->assertArrayHasKey('name2', $result);
        $this->assertEquals('Mohammed', $result['name2']);
    }


    public function testCanHydrateObjectUsingEnumObject()
    {
        $sql = <<<SQL
        WITH cus AS (
            SELECT *, 'monday' as day FROM customer WHERE id = $(id)
        )
        SELECT cus FROM cus
SQL;

        $dbal = $this->getDBAL();

        $stmt = $dbal->prepare($sql);

        $stmt->setParameter('id', 1)
            ->hydrate('cus', Customer2::class)
            ->execute();

        $result = $stmt->fetchColumn();

        $expected = new Customer2();
        $expected->id = 1;
        $expected->name = 'Albert';
        $expected->created_at = new \DateTimeImmutable('2018-01-01 10:10:10.000000');
        $expected->day = new Day('monday');

        $this->assertEquals($expected, $result);
    }

    public function testCanUseTransaction()
    {
        $sql = "UPDATE customer SET name = 'bla bla' WHERE id = 1";

        $dbal = $this->getDBAL();

        $dbal->beginTransaction();
        $stmt = $dbal->prepare($sql);
        $stmt->execute();

        $stmt = $dbal->prepare("select name from customer WHERE id = 1");
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $this->assertEquals('bla bla', $name);

        $dbal->rollBack();

        $stmt = $dbal->prepare("select name from customer WHERE id = 1");
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $this->assertEquals('Albert', $name);
    }

    public function testCanUseTransactionAndSavePoint()
    {
        $dbal = $this->getDBAL();

        $dbal->beginTransaction();
        $stmt = $dbal->prepare("INSERT INTO customer VALUES (99, 'Alain', NOW())");
        $stmt->execute();

        $dbal->addSavePoint('beforeUpdate');

        $stmt = $dbal->prepare("UPDATE customer SET name = 'bla bla' WHERE id = 1");
        $stmt->execute();

        $stmt = $dbal->prepare("select name from customer WHERE id = 1");
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $this->assertEquals('bla bla', $name);

        $dbal->rollBack('beforeUpdate');

        $stmt = $dbal->prepare("select name from customer WHERE id = 1");
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $this->assertEquals('Albert', $name);

        $dbal->commit();

        $stmt = $dbal->prepare("select name from customer WHERE id = 99");
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $this->assertEquals('Alain', $name);

    }

    private function getDBAL(): Dbal
    {
        return new Dbal($this->getSerializer(), $this->getDsn());
    }

    /**
     * @return string
     */
    private function getDsn(): string
    {
        return getenv('DATABASE_DSN');
    }
}