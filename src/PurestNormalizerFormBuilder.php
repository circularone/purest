<?php

namespace Drupal\purest;

use Drupal\user\UserInterface;
use Drupal\Core\Form\FormState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Url;

/**
 * Class PurestNormalizerFormBuilder.
 */
class PurestNormalizerFormBuilder implements PurestNormalizerFormBuilderInterface {

  /**
  * Config factory interface.
  *
  * @var  ConfigFactoryInterface
  */
  protected $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManager $entity_field_manager) {
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityFieldsTable($entity_type, $bundle, &$form, FormState $form_state, $config) {
    $entity_fields = $this->entityFieldManager
      ->getFieldDefinitions($entity_type, $bundle);
    $values = $config->get('fields');

    $form['fields_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Field Settings'),
    ];

    $form['fields'] = [
      '#title' => t('Entity Fields'),
      '#type' => 'table',
      '#sticky' => TRUE,
      '#header' => [
        t('Name'),
        t('Label'),
        t('Type'),
        t('Custom Label'),
        t('Hide if Empty'),
        t('Exclude'),
        t('Operations'),
      ],
    ];

    foreach ($entity_fields as $field_name => $field_definition) {
      // Never allow password type fields.
      if ($field_definition->getType() === 'password') {
        continue;
      }

      $this->defaultFieldSettings(
        $form,
        $form_state,
        $values,
        'fields',
        $field_name,
        $field_definition
      );

      $fieldType = $field_definition->getType();

      // @Todo datetime fields should be similar to image fields and allow
      // multiple formats in each field. This includes created and changed
      // e.g. when viewing an article you would want a human readable version
      // for display and another version for open graph tags.
      switch ($fieldType) {
        case 'image':
        // case 'entity_reference':
        // case 'file':
        // case 'link':
        // case 'datetime':
        // case 'created':
        // case 'changed':
        // case 'path':
          $routeName = 'purest.normalizer.entity.field.' . $fieldType;

          $form['fields'][$field_name]['actions'] = [
            '#type' => 'dropbutton',
            '#links' => [
              'edit' => [
                'title' => t('Edit'),
                'url' => Url::fromRoute($routeName, [
                  'type' => $entity_type,
                  'bundle' => $bundle,
                  'field' => $field_name,
                ]),
              ],
            ],
          ];
          break;

        default:
          $form['fields'][$field_name]['actions'] = [
            '#type' => 'dropbutton',
            '#links' => [],
          ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultFieldSettings(&$form, FormState $form_state, $values, $form_key, $field_name, $field_definition) {

    $form['fields'][$field_name]['name'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $field_definition->getLabel(),
    ];

    if ($field_name == 'type') {
      $form['fields'][$field_name]['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => json_encode($field_definition->getClass()),
      ];
    }
    else {

    $form['fields'][$field_name]['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $field_name,
    ]; }

    $form['fields'][$field_name]['type'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#size' => 20,
      '#default_value' => $field_definition->getType(),
    ];

    $form['fields'][$field_name]['custom_label'] = [
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => NULL !== $values[$field_name]['custom_label'] ?
        $values[$field_name]['custom_label'] : '',
    ];

    $form['fields'][$field_name]['hide_empty'] = [
      '#type' => 'checkbox',
      '#default_value' => NULL !== $values[$field_name]['hide_empty'] ?
        intval($values[$field_name]['hide_empty']) : 0,
    ];

    $form['fields'][$field_name]['exclude'] = [
      '#type' => 'checkbox',
      '#default_value' => NULL !== $values[$field_name]['exclude'] ?
        intval($values[$field_name]['exclude']) : 0,
    ];
  }

}
