<?php

namespace Drupal\purest_user\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\user\UserInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Drupal\serialization\Normalizer\FieldableEntityNormalizerTrait;

/**
 * Converts the Drupal User entity object structure to an array structure.
 */
class UserNormalizer extends NormalizerBase implements DenormalizerInterface {

  use FieldableEntityNormalizerTrait;

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\user\UserInterface';

  /**
   * Constructs an EntityNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->get('purest_user.settings');
    $options = $config->get('fields');
    
    $output = [
      'uid' => (int) $entity->id(),
      'uuid' => $entity->uuid(),
      'langcode' => $entity->get('langcode')->value,
      'preferred_langcode' => $entity->getPreferredLangcode(),
      'preferred_admin_langcode' => $entity->getPreferredAdminLangcode(),
      'default_langcode' => (bool) $entity->get('default_langcode')->value,
      'name' => $entity->get('name')->value,
      'mail' => $entity->getEmail(),
      'timezone' => $entity->getTimezone(),
      'status' => (bool) $entity->isBlocked(),
      'created' => (int) $entity->getCreatedTime(),
      'changed' => (int) $entity->getChangedTime(),
      'access' => (int) $entity->getLastAccessedTime(),
      'login' => (int) $entity->getLastLoginTime(),
      'init' => $entity->getInitialEmail(),
      'roles' => $entity->getRoles(),
    ];

    if ($options === NULL) {
      return $output;
    }

    foreach ($options as $key => $option) {
      if (intval($output['exclude'])) {
        unset($options[$key]);
        continue;
      }

      if (intval($option['hide_empty']) && !$output[$key]) {
        unset($output[$key]);
        continue;
      }

      if (!isset($output[$key])) {
        $output[$key] = $entity->get($key)->value;
      }

      if ($option['custom_label']) {
        $output[$option['custom_label']] = $output[$key];
        unset($output[$key]);
      }
    }

    return $output;
  }

  /**
   * Implements \Symfony\Component\Serializer\Normalizer\DenormalizerInterface::denormalize().
   *
   * @param array $data
   *   Entity data to restore.
   * @param string $class
   *   The class of the entity to be denormalized.
   * @param string $format
   *   Format the given data was extracted from.
   * @param array $context
   *   Options available to the denormalizer. Keys that can be used:
   *   - request_method: if set to "patch" the denormalization will clear out
   *     all default values for entity fields before applying $data to the
   *     entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An unserialized entity object containing the data in $data.
   *
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $entity_type_id = $this->determineEntityTypeId($class, $context);

    $entity_type_definition = $this->getEntityTypeDefinition($entity_type_id);

    // The bundle property will be required to denormalize a bundleable
    // fieldable entity.
    if ($entity_type_definition->isSubclassOf(FieldableEntityInterface::class)) {
      // Extract bundle data to pass into entity creation if the entity type uses
      // bundles.
      if ($entity_type_definition->hasKey('bundle')) {
        // Get an array containing the bundle only. This also remove the bundle
        // key from the $data array.
        $create_params = $this->extractBundleData($data, $entity_type_definition);
      }
      else {
        $create_params = [];
      }

      // Create the entity from bundle data only, then apply field values after.
      $entity = $this->entityManager->getStorage($entity_type_id)->create($create_params);

      $this->denormalizeFieldData($data, $entity, $format, $context);
    }
    else {
      // Create the entity from all data.
      $entity = $this->entityManager->getStorage($entity_type_id)->create($data);
    }

    // Pass the names of the fields whose values can be merged.
    // @todo https://www.drupal.org/node/2456257 remove this.
    $entity->_restSubmittedFields = array_keys($data);

    return $entity;
  }

}
