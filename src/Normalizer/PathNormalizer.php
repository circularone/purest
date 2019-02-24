<?php

namespace Drupal\purest\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\path\Plugin\Field\FieldType\PathItem;

/**
 * Normalizes path items.
 */
class PathNormalizer extends FieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = PathItem::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $value = $field_item->getValue();
    return $value['alias'] ?? $value['source'] ?? '';
  }
}
