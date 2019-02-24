<?php

namespace Drupal\purest_content\Form;

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

/**
 * Class NormalizerSettingsForm.
 */
class NormalizerSettingsForm extends ConfigFormBase {

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
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, EntityTypeManagerInterface $type_manager, EntityTypeBundleInfo $type_bundle, EntityFieldManager $entity_field_manager, PurestNormalizerFormBuilderInterface $purest_form_builder) {
    parent::__construct($config_factory, $state);
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->entityTypeManager = $type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundle = $type_bundle;
    $this->purestFormBuilder = $purest_form_builder;

    $this->entity_types = [
      'node' => $this->entityTypeBundle->getBundleInfo('node'),
      'taxonomy_term' => $this->entityTypeBundle->getBundleInfo('taxonomy_term'),
    ];
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('purest.normalizer_form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'purest_content.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purest_content_settings_form';
  }

  /**
   *
   */
  public function getTitle() {
    $bundle = \Drupal::routeMatch()->getParameter('bundle');
    return ucfirst(str_replace('_', ' ', $bundle)) . ' Resource Settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = NULL, $bundle = NULL) {
    if (!$type || !$bundle) {
      return new RedirectResponse(\Drupal::url('purest_content.config'));
    }

    $this->type = $type;
    $this->bundle = $bundle;

    $this->configId = 'purest_content.' . $this->type . '.' . $this->bundle;
    $config = $this->configFactory->get($this->configId);

    $form['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => ucfirst(str_replace('_', ' ', $bundle)) . t(' Field Settings'),
    ];

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('Use this form to customize or exclude the output of each field.'),
    ];

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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->configFactory
      ->getEditable($this->configId);

    $config->set('normalize', $form_state
      ->getValue('normalize'));
    $config->set('fields', $form_state
      ->getValue('fields'));

    $config->save();
  }

}
