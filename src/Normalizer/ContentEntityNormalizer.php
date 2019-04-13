<?php

namespace Drupal\purest\Normalizer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer as CoreContentEntityNormalizer;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\CountConstraint;

/**
 * Converts the Drupal entity object structures to a normalized array.
 */
class ContentEntityNormalizer extends CoreContentEntityNormalizer {

  /**
   * Config factory
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityTypeBundle;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   */
  public function __construct(
    EntityManagerInterface $entity_manager,
    ConfigFactoryInterface $config_factory,
    EntityTypeBundleInfo $entity_type_bundle,
    EntityFieldManager $entity_field_manager) {
    parent::__construct($entity_manager, $config_factory);
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('purest_content.settings');
    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundle = $entity_type_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    // If we aren't dealing with an object or the format is not
    // supported return now.
    if (!is_object($data) || !$this->checkFormat($format)) {
      return FALSE;
    }

    // This normalizer only supports objects that implement the ContentEntityInterface.
    if ($data instanceof ContentEntityInterface) {
      return TRUE;
    }

    // Otherwise, this normalizer does not support the $data object.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $config_name = 'purest.normalizer.';
    $config_name .= $entity->getEntityTypeId() . '.' . $entity->bundle();
    $entity_config = $this->configFactory->get($config_name);
    $fields_config = $entity_config->get('fields');

    $context += [
      'account' => NULL,
    ];
    $attributes = [];

    $values = parent::normalize($entity, $format, $context);

    /** @var \Drupal\Core\Entity\Entity $entity */
    foreach (TypedDataInternalPropertiesHelper::getNonInternalProperties($entity
      ->getTypedData()) as $name => $field_item) {

      if (isset($values[$name]) && $field_item->access('view', $context['account'])) {
        $value = $values[$name];

        // Get the cardinality of fielditemlists and return the first item only
        // if cardinality is 1.
        if ($field_item instanceof FieldItemListInterface) {
          $field_settings = $field_item->getConstraints();

          foreach ($field_settings as $setting) {
            if ($setting instanceof CountConstraint) {
              if ($setting->max === 1) {
                $value = isset($value[0]) ? $value[0] : NULL;
              }
            }
          }
        }

        // If the value is an associative array with 'value' as only key, return
        // the value of 'value'. Check for end value as it may be a range field.
        if (is_array($value) && isset($value['value']) && !isset($value['end_value'])) {
          if (isset($value['processed'])) {
            $value = $value['processed'];
          }
          else {
            $value = $value['value'];
          }
        }

        if (isset($fields_config[$name])) {
          if (intval($fields_config[$name]['exclude'])) {
            continue;
          }

          $hide_empty = intval($fields_config[$name]['hide_empty']);

          if ($hide_empty && ($value === NULL || empty($value))) {
            $is_object = is_array($value);
            continue;
          }

          if ($fields_config[$name]['custom_label']) {
            $attributes[$fields_config[$name]['custom_label']] = $value;
            continue;
          }

          $attributes[$name] = $value;
        }
        else {
          $attributes[$name] = $value;
        }
      }
    }

    return $attributes;
  }

  private function removeCardinality(&$entity) {
    foreach ($entity as $key => $value) {

    }
  }

}
