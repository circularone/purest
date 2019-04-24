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
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "purest_entity_definition_resource",
 *   label = @Translation("Purest Entity Definition Resource"),
 *   uri_paths = {
 *     "canonical" = "/purest/entity-form",
 *   }
 * )
 */
class EntityDefinitionResource extends ResourceBase {

  /**
   * Entity type manager interface.
   *
   * Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The current request stack.
   * 
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundle;

  /**
   * Form builder interface.
   * 
   * @var Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $formBuilder;

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
   * @param \Drupal\Core\Entity\EntityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity type manager interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack
   *   The current request stack.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface
   *   Form builder interface.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityFieldManager $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request,
    EntityTypeBundleInfo $entity_type_bundle,
    EntityFormBuilderInterface $form_builder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request->getCurrentRequest();
    $this->entityTypeBundle = $entity_type_bundle;
    $this->formBuilder = $form_builder;
    $this->entity_types = [];
    $entities = $this->entityTypeManager->getDefinitions();

    foreach ($entities as $key => $val) {
      if ($val instanceof ContentEntityType) {
        $keys = $val->getKeys();

        $class = $val->getClass();

        if (!empty($keys)) {
          $this->entity_types[$key] = [
            'title' => $val->getLabel(),
            'bundles' => $this->entityTypeBundle->getBundleInfo($key),
            'class' => $val->getClass(),
          ];
        }
      }
    }
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
      $container->get('logger.factory')->get('purest'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Responds to GET requests.
   *
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    $entity_type = $this->request->query->get('entity');
    $bundle = $this->request->query->get('bundle');
    $mode = $this->request->query->get('mode');

    if (!array_key_exists($entity_type, $this->entity_types)) {
      return new ModifiedResourceResponse([
        'error' => 'Entity type not found',
      ], 404);
    }

    if ($bundle && !array_key_exists($bundle, $this->entity_types[$entity_type]['bundles'])) {
      return new ModifiedResourceResponse([
        'error' => 'Bundle not found',
      ], 404);
    }

    if ($bundle) {
      $arguments = ['type' => $bundle];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $entity =  $this->entity_types[$entity_type]['class']::create(['type' => $bundle]);
    $form = $this->formBuilder->getForm($entity, $mode);
    $form_keys = array_keys($form);
    $form_elements = [];
    
    foreach ($form as $key => $value) {
      if (isset($value['#access']) && !$value['#access']) {
        continue;
      }

      if (!isset($fields[$key]) || !($fields[$key] instanceof BaseFieldDefinition || $fields[$key] instanceof FieldConfig)) {
        continue;
      }

      $element = $fields[$key]->toArray() + [
        'weight' => $value['#weight'] + 100000,
        'settings' => $fields[$key]->getSettings(),
        'constraints' => $fields[$key]->getConstraints(),
        'attributes' => $value['#attributes'],
      ];

      if ($fields[$key] instanceof FieldConfig) {
        $typed_data = $fields[$key]->getFieldStorageDefinition();
        $element['cardinality'] = $typed_data->getCardinality();
        $element['type'] = $typed_data->getType();
      }

      if ($fields[$key] instanceof BaseFieldDefinition) {
        $element['cardinality'] = $fields[$key]->getCardinality();
      }

      // if (isset($element['display']['form']['options']['type'])) {
      //   $element['type'] = $element['display']['form']['options']['type'];
      // }

      switch ($element['type']) {
        case 'list_string':
          $element['sub_type']= 'select';
          break;

        case 'string':
          $element['sub_type'] = 'text';
          break;

        case 'text_long':
          $element['sub_type'] = 'textarea';
          break;

        case 'boolean':
          $element['sub_type'] = 'checkbox';
          break;

        case 'entity_reference':
          if ($element['settings']['handler'] === 'default:taxonomy_term') {
            $vocab = array_keys($element['settings']['handler_settings']['target_bundles']);
            $vocab = reset($vocab);

            $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
              'status' => 1,
              'vid' => $vocab,
            ]);

            if (isset($element['settings'])) {
              $element['settings'] = [];
            }

            $element['settings']['allowed_values'] = [];

            foreach ($terms as $term) {
              $element['settings']['allowed_values'][$term->id()] = $term->get('name')->value;
            }
          }
          break;
      }

      $element['name'] = $element['field_name'];

      $remove_fields = ['revisionable','provider','entity_type','bundle','field_name'];

      foreach ($remove_fields as $remove_key) {
        if (array_key_exists($remove_key, $element)) {
          unset($element[$remove_key]);
        }
      }

      if (isset($element['default_value']) && isset($element['default_value'][0]) && isset($element['default_value'][0]['value'])) {
        $element['default_value'] = $element['default_value'][0]['value'];
      }

      $form_elements[] = $element;
    }

    if (!empty($form_elements)) {
      usort($form_elements, [$this, 'sortByWeight']);
    }

    $response = new ModifiedResourceResponse([
      'fields' => $form_elements,
    ], 200);
    
    return $response;

  }

  public function sortByWeight($a, $b) {
    return $a['weight'] - $b['weight'];
  }

}