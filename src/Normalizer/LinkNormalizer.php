<?php

namespace Drupal\purest\Normalizer;

use Drupal\serialization\Normalizer\TypedDataNormalizer as SerializationTypedDataNormalizer;
use Drupal\link\LinkItemInterface;

/**
 * Normalizes link interface items.
 */
class LinkNormalizer extends SerializationTypedDataNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = LinkItemInterface::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    return [
      'url' => $object->getUrl()->toString(),
      'external' => $object->isExternal()
    ];
  }

}
