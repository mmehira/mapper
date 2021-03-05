<?php

namespace SimpleMapper\Tests\Model;

use SimpleMapper\Type\Enum\Day;

class Customer2 {
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var \DateTimeInterface
     */
    public $created_at;

    /**
     * @var Day
     */
    public $day;

}