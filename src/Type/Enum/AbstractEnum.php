<?php

namespace SimpleMapper\Type\Enum;

abstract class AbstractEnum
{
    final public function __construct($value = null)
    {
        if ($value === null && defined(static::class . '::__default')) {
            $this->value = constant(static::class . '::__default');
            return;
        }
        $c = new \ReflectionClass($this);
        if (!in_array($value, $c->getConstants())) {
            throw new \UnexpectedValueException(sprintf('Value "%s" not a const in enum %s', $value, __CLASS__));
        }
        $this->value = $value;
    }

    final public function __toString()
    {
        return $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public static function getAvailableValues($withKeys = true)
    {
        $c = new \ReflectionClass(get_called_class());
        $constants = $c->getConstants();
        if (defined(static::class . '::__default')) {
            unset($constants['__default']);
        }

        if (!$withKeys) {
            return array_values($constants);
        }

        return $constants;
    }

}
