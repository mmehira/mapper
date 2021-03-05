<?php

namespace SimpleMapper\Serializer\Normalizer;

use App\Postgres\Type\DateTimeRange;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DateTimeRangeNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = array()): string
    {
        /** @var $datetimerange DateTimeRange */
        $datetimerange = $object;

        $signBefore = $datetimerange->isIncludedFrom() ? '[' : '(';
        $signAfter = $datetimerange->isIncludedTo() ? ']' : ')';

        $from = $datetimerange->getfrom()->format(DATE_ISO8601);
        $to = $datetimerange->getto()->format(DATE_ISO8601);

        return sprintf("'%s\"%s\",\"%s\"%s'", $signBefore, $from, $to, $signAfter);
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof DateTimeRange;
    }
}