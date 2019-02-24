<?php

namespace Drupal\purest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\purest\PurestNormalizerFormBuilderInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Class EntityFieldsNormalizerForm.
 */
class EntityFieldNormalizerForm extends ConfigFormBase {

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Purest normalizer entity fields form builder.
   *
   * @var \Drupal\purest\PurestNormalizerFormBuilderInterface
   */
  protected $purestFormBuilder;

  /**
   * ID of the entity type this class deals with.
   *
   * @var string
   */
  public $type;

  /**
   * ID of the entity variation.
   *
   * @var string
   */
  public $bundle;

  /**
   * ID of the entity config.
   *
   * @var string
   */
  public $configId;

  /**
   * Constructs a new ConfigForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    EntityTypeManagerInterface $type_manager,
    EntityTypeBundleInfo $type_bundle,
    EntityFieldManager $entity_field_manager,
    PurestNormalizerFormBuilderInterface $purest_form_builder,
    RouteMatchInterface $route_match) {
    parent::__construct($config_factory, $state);
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->entityTypeManager = $type_manager;
    $this->entityTypeBundle = $type_bundle;
    $this->entityFieldManager = $entity_field_manager;
    $this->purestFormBuilder = $purest_form_builder;
    $this->routeMatch = $route_match;
    $this->type = $this->routeMatch->getParameter('type');
    $this->bundle = $this->routeMatch->getParameter('bundle');
    $this->configId = 'purest.normalizer.' . $this->type . '.' . $this->bundle;

    // Get all content entity types and all bundles for that type.
    $this->entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $key => $val) {
      if ($val instanceof ContentEntityType) {
        $keys = $val->getKeys();
        if ($keys) {
          $this->entity_types[$key] = [
            'title' => $val->getLabel(),
            'bundles' => $this->entityTypeBundle->getBundleInfo($key),
          ];
        }
      }
    }

    // Redirect to the purest.normalizer page if entity type or bundle doesn't exist.
    if (!isset($this->entity_types[$this->type])
      || !isset($this->entity_types[$this->type]['bundles'][$this->bundle])) {
      drupal_set_message(t('A valid entity type and bundle must be specified.'), 'error');
      return new RedirectResponse(\Drupal::url('purest.normalizer'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('purest.normalizer_form_builder'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this->configId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purest_content_settings_form';
  }

  /**
   * Returns the title for an entity type bundle.
   */
  public function getTitle() {
    $title = $this->entity_types[$this->type]['bundles'][$this->bundle]['label'];

    // Entity type label can sometimes match the bundle label so skip if matched.
    if (strtolower($title) !== strtolower($this->entity_types[$this->type]['title'])) {
      $title .= ' ' . $this->entity_types[$this->type]['title'];
    }

    $title .= ' ' . t('Field Settings');
    return ucwords($title);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = NULL, $bundle = NULL) {
    $config = $this->configFactory->get($this->configId);

    $form['toggle_normalizer'] = [
      '#type' => 'field_group',
      '#title' => ucfirst($bundle) . ' [' . $type . ']',
    ];

    $form['toggle_normalizer']['normalize_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Purest Normalizer'),
    ];

    $normalize = $config->get('normalize');

    $form['toggle_normalizer']['normalize'] = [
      '#title' => t('Use Purest normalizer'),
      '#description' => t('Turn this off to exlude this entity type from normalization.'),
      '#type' => 'checkbox',
      '#default_value' => NULL === $normalize ? 1 : $normalize,
    ];

    $this->purestFormBuilder->buildEntityFieldsTable(
      $this->type,
      $this->bundle,
      $form,
      $form_state,
      $config
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->configFactory->getEditable($this->configId);
    $config->set('normalize', $form_state->getValue('normalize'));
    $config->set('fields', $form_state->getValue('fields'));
    $config->save();
  }

}
