<?php

namespace SimpleMapper\Serializer\Normalizer;

use SimpleMapper\Type\Enum\AbstractEnum;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EnumNormalizer implements NormalizerInterface
{
    /**
     * @inheritdoc
     */
    public function normalize($object, $format = null, array $context = array())
    {
        return $object->getValue();
    }

    /**
     * @inheritdoc
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && $data instanceof AbstractEnum;
    }
}