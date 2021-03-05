<?php

namespace SimpleMapper\Type\Range;

interface RangeInterface
{
    public function getfrom();

    public function getTo();

    public function isIncludedFrom(): bool;

    public function isIncludedTo(): bool;
}