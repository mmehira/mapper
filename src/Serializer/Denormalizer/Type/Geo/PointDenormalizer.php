<?php

namespace Mapper\Serializer\Denormalizer\Type\Geo;

use Mapper\Type\Geometry\Point;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PointDenormalizer implements DenormalizerInterface
{

    /**
     * @inheritdoc
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        [$x, $y] = sscanf($data, '(%f,%f)');

        return new Point($x, $y);
    }

    /**
     * @inheritdoc
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $type == Point::class;
    }
}