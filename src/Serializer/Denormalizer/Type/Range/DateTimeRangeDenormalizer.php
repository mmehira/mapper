<?php

namespace SimpleMapper\Serializer\Denormalizer\Type\Range;

use SimpleMapper\Type\Range\DateTimeRange;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;


class DateTimeRangeDenormalizer implements DenormalizerInterface
{
    private const FIELD_NAME = 'tstzrange';

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $re = '/^([\[\(])\"{0,1}([0-9]{4}\-[0-9]{2}\-[0-9]{2}\s+[0-9]{2}\:[0-9]{2}\:[0-9]{2}\.{0,1}[0-9]{0,}\+[0-9]{2})\"{0,1}\,\"{0,1}([0-9]{4}\-[0-9]{2}\-[0-9]{2}\s+[0-9]{2}\:[0-9]{2}\:[0-9]{2}\.{0,1}[0-9]{0,}\+[0-9]{2})\"{0,1}([\]\)])$/m';

        preg_match_all($re, $data, $matches, PREG_SET_ORDER, 0);

        if (count($matches) === 0) {
            throw new \InvalidArgumentException(sprintf('"%s" is not valid a %s value', $data, self::FIELD_NAME));
        }

        [,$signBefore, $dateFrom, $dateTo,$signAfter] = $matches[0];

        return new DateTimeRange(
            new \DateTime($dateFrom),
            new \DateTime($dateTo),
            $signBefore === '[',
            $signAfter === ']'
        );
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_string($data) && DateTimeRange::class === $type;
    }
}