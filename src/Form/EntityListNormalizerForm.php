<?php

namespace Drupal\purest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityType;

/**
 * Class EntityListNormalizerForm.
 */
class EntityListNormalizerForm extends ConfigFormBase {

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
   * Constructs a new ConfigForm object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfo $entity_type_bundle) {
    parent::__construct($config_factory, $state);
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundle = $entity_type_bundle;

    // Get all content entity types and all bundles for that type.
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

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'purest.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purest_entity_list_normalizer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('purest.settings');

    $form['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Entity Type Bundles'),
    ];

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Select entity type bundle to edit the normalizer settings for that bundle.'),
    ];

    $form['entity_list'] = [
      '#title' => $this->t('Entity Fields'),
      '#type' => 'table',
      '#sticky' => TRUE,
      '#header' => [
        $this->t('Bundle / Type'),
        $this->t('Entity Type'),
        $this->t('Operations'),
      ],
    ];

    foreach ($this->entity_types as $entity_type_id => $entity_type) {
      foreach ($entity_type['bundles'] as $bundle_id => $bundle) {
        $form['entity_list'][$entity_type_id . '__' . $bundle_id]['name'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $bundle_id,
        ];

        $form['entity_list'][$entity_type_id . '__' . $bundle_id]['type'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $entity_type_id,
        ];

        $form['entity_list'][$entity_type_id . '__' . $bundle_id]['actions'] = [
          '#type' => 'dropbutton',
          '#links' => [
            'edit' => [
              'title' => $this
                ->t('Edit'),
              'url' => Url::fromRoute('purest.normalizer.entity', [
                'type' => $entity_type_id,
                'bundle' => $bundle_id,
              ]),
            ],
          ],
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

}
