<?php

namespace SimpleMapper\Serializer\Denormalizer;

use SimpleMapper\Type\Enum\AbstractEnum;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class EnumDenormalizer implements DenormalizerInterface
{

    /**
     * @inheritdoc
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return new $class($data);
    }

    /**
     * @inheritdoc
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_string($data) && is_subclass_of($type, AbstractEnum::class);
    }
}