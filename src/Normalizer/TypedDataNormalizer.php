<?php

namespace Drupal\purest\Normalizer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\serialization\Normalizer\TypedDataNormalizer as SerializationTypedDataNormalizer;
use Drupal\Core\Field\Plugin\Field\FieldType\CreatedItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\views\Views;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Converts typed data objects to arrays.
 */
class TypedDataNormalizer extends SerializationTypedDataNormalizer {

  /**
   * The normalizer used to normalize the typed data.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $serializer;

  /**
   * Indicates if parent is being serialized.
   *
   * @var boolean
   */
  protected $serializingParent = FALSE;

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    // Check typed data normalizer is allowed in Purest main config
    // $config = \Drupal::service('config.factory')->get('purest.settings');
    // $normalizers_on = $config->get('normalize');

    // if ($normalizers_on !== NULL && !$normalizers_on) {
    //   return FALSE;
    // }


    if ($this->serializingParent) {
      // $this->serializingParent = FALSE;
      // Let parent handle it.
      return FALSE;
    }

    return parent::supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {

    /* @var $object \Drupal\Core\TypedData\TypedDataInterface */
    if (!$this->serializer) {
      $this->serializer = \Drupal::service('serializer');
    }

    $this->serializingParent = TRUE;
    $value = $this->serializer->normalize($object, $format, $context);
    $this->serializingParent = FALSE;

    $this->addCacheableDependency($context, $object);

    // Support for stringable value objects: avoid numerous custom normalizers.
    if (is_object($value) && method_exists($value, '__toString')) {
      $value = (string) $value;
    }

    // If this is a field with never more then 1 value, show the first value.
    if ($object instanceof FieldItemListInterface) {
      $cardinality = $object->getFieldDefinition()
                                ->getFieldStorageDefinition()->getCardinality();

      if ($cardinality === 1) {
        if (isset($value[0])) {
          $value = $value[0];
        }
        else {
          $value = NULL;
        }
      }
    }

    // If the value is an associative array with 'value' as only key, return the
    // value of 'value'. Check for end value as it may be a range field.
    if (is_array($value) && isset($value['value']) && !isset($value['end_value'])) {
      if (isset($value['processed'])) {
        $value = $value['processed'];
      }
      else {
        $value = $value['value'];
      }
    }

    return $value;
  }
}
