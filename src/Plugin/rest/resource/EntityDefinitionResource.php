<?php

namespace Drupal\purest\Plugin\rest\resource;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\rest\Plugin\Deriver\EntityDeriver;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @see \Drupal\rest\Plugin\Deriver\EntityDeriver
 *
 * @RestResource(
 *   id = "purest_entity_definition_resource",
 *   label = @Translation("Purest Entity Definition Resource"),
 *   uri_paths = {
 *     "canonical" = "/purest/{entity_type}/{type}",
 *   }
 * )
 */
class EntityDefinitionResource extends ResourceBase {

  /**
   * The entity type targeted by this resource.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Constructs a new EntityDefinitionResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityFieldManager $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('fts_rest'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    if (!$type_bundle || strpos($type_bundle, '-')) {
      return new ResourceResponse([t('Content not found')], 404);
    }

    $type_bundle = explode('-');
    $type = $type_bundle[0];
    $bundle = $type_bundle[1];

    $fields = $this->entityFieldManager->getFieldDefinitions($type, $bundle);
    $output = 'foo';

    // $output = [];
    // foreach ($this->fields as $field_name => $field_definition) {
    //   if (in_array($field_name, $excluded_fields)) {
    //     continue;
    //   }

    //   $output[$field_name] = [
    //     'label' => $field_definition->getLabel(),
    //     'type' => $field_definition->getType(),
    //     'required' => $field_definition->isRequired(),
    //     'settings' => $field_definition->getSettings(),
    //   ];


    //   if ($field_definition instanceof BaseFieldDefinition) {
    //     // $output[$field_name]['multiple'] = $field_definition->getCardinality();
    //   }
    //   else {
    //     $field_config = FieldStorageConfig::load($field_definition->getEntityTypeId(), $field_name);
    //     if ($field_config !== NULL) {
    //       $output[$field_name]['multiple'] = $field_config->getCardinality();
    //     }
    //   }

      // if ($output[$field_name]['type'] === 'address') {
      //   $output[$field_name]['test'] = $field_definition;
      // }
    // }

    return new ResourceResponse($output, 200);
  }


}
