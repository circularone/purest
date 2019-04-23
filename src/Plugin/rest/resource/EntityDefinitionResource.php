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
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @see \Drupal\rest\Plugin\Deriver\EntityDeriver
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

  protected $request;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityTypeBundle;

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

    foreach ($this->entityTypeManager->getDefinitions() as $key => $val) {
      if ($val instanceof ContentEntityType) {
        $keys = $val->getKeys();

        if (!empty($keys)) {
          $this->entity_types[$key] = [
            'title' => $val->getLabel(),
            'bundles' => $this->entityTypeBundle->getBundleInfo($key),
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
    $entity_type = $this->request->query->get('entity');
    $bundle = $this->request->query->get('bundle');
    $mode = $this->request->query->get('mode');

    $output = [
      'type' => $entity_type,
      'bundle' => $bundle,
      'mode' => $mode,
      'form_id' => $entity_type . '.' . ($bundle ?? 'default') . '.default',
      'form' => [],
      'classes' => [],
    ];

    if (!array_key_exists($entity_type, $this->entity_types)) {
      return new ResourceResponse(['error' => t('Entity type does not exists')], 406);
    }


    if ($bundle && !array_key_exists($bundle, $this->entity_types[$entity_type]['bundles'])) {
      return new ResourceResponse(['error' => t('Bundle does not exist')], 406);
    }

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

    $exclude = [
      'nid',
      'tid',
      'id',
      'job_id',
      'uuid',
      'vid',
      'langcode',
      'type',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'status',
      'uid',
      'created',
      'changed',
      'updated',
      'promote',
      'sticky',
      'default_langcode',
      'revision_default',
      'revision_translation_affected',
      'metatag',
      'field_meta',
      'menu_link',
      'path',
      'field_menu',
      'field_views',
      'revision_created',
      'user_id',
      'revision_log_message',
      'revision_user',
      'lumesse_id',
      'job',
      'field_xml',
      'job_number',
      'head_count',
      'language',
      'field_applications',
      'preferred_langcode',
      'preferred_admin_langcode',
      'field_gdpr',
      'field_lumesse_username',
      'field_lumesse_password',
      'field_lumesse_activated',
      'field_myaccount',
      'field_organisations',
      'field_clicks',
      'field_coordinates',
      'field_featured',
      'field_featured_apply',
      'field_featured_click',
      'field_job_centre_plus',
      'field_promoted',
      'access',
      'login',
      'roles',
      'timezone',
      'init',
    ];

    foreach ($fields as $key => $value) {
      $output['classes'][] = get_class($value);
      if (in_array($key, $exclude)) continue;
      if ($entity_type === 'user' && $key === 'name') continue;

      if ($value instanceof BaseFieldDefinition) {
        $output['form'][$key] = $value->toArray();
        $output['form'][$key]['field_type'] = $value->getType();
        //   'multiple' => $value->isMultiple(),
        //   // 'default' => $value->getDefaultValue(),
        //   'key' => $value->getName(),
        //   'label' => $value->getLabel(),
        //   'description' => $value->getDescription() ?? '',
        //   'required' => $value->isRequired(),
        //   'disabled' => $value->isReadOnly(),
        //   'data_type' => $value->getDataType(),
        //   'constraints' => $value->getConstraints(),
        // ];
      }

      if ($value instanceof FieldConfig) {
        $output['form'][$key] = $value->toArray();

        if ($value->getType() === 'list_string') {
          $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->load(str_replace('field.storage.', '', $output['form'][$key]['dependencies']['config'][0]));

          $output['form'][$key]['allowed_values'] = $field_storage->getSetting('allowed_values');
        }
      }

      switch ($output['form'][$key]['field_type']) {
        case 'list_string':
          $output['form'][$key]['field_type'] = 'select';
          break;
        case 'string':
          $output['form'][$key]['field_type'] = 'text';
          break;
        case 'text_long':
          $output['form'][$key]['field_type'] = 'textarea';
          break;
        case 'boolean':
          $output['form'][$key]['field_type'] = 'checkbox';
          break;
        case 'entity_reference':
          if ($output['form'][$key]['settings']['handler'] === 'default:taxonomy_term') {
            $vocab = array_keys($output['form'][$key]['settings']['handler_settings']['target_bundles']);
            $vocab =reset($vocab);

            $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
              'status' => 1,
              'vid' => $vocab,
            ]);

            $output['form'][$key]['allowed_values'] = [];

            foreach ($terms as $term) {
              $output['form'][$key]['allowed_values'][$term->id()] = $term->get('name')->value;
            }
          }
          break;
      }


    }

    return new ResourceResponse($output, 200);
  }


}
