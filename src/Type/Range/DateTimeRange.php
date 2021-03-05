<?php

namespace SimpleMapper\Type\Range;

class DateTimeRange implements RangeInterface
{
    private $from;
    private $to;

    private $includedFrom;
    private $includedTo;

    public function __construct(\DateTimeInterface $from, \DateTimeInterface $to, bool $includedFrom = true, bool $includedTo = false)
    {
        if($to <= $from) {
            throw new \InvalidArgumentException(sprintf("Date %s is not greater than %s.", $from->format(DATE_W3C), $to->format(DATE_W3C)));
        }

        $this->from         = $from;
        $this->to           = $to;
        $this->includedFrom = $includedFrom;
        $this->includedTo   = $includedTo;
    }

    public function getfrom(): \DateTimeInterface
    {
        return $this->from;
    }

    public function getTo(): \DateTimeInterface
    {
        return $this->to;
    }

    public function isIncludedFrom(): bool
    {
        return $this->includedFrom;
    }

    public function isIncludedTo(): bool
    {
        return $this->includedTo;
    }
}