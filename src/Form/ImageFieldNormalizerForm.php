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
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Class ImageFieldNormalizerForm.
 */
class ImageFieldNormalizerForm extends ConfigFormBase {

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
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Constructs a new FieldNormalizerSettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    EntityTypeManagerInterface $type_manager,
    EntityTypeBundleInfo $type_bundle,
    EntityFieldManager $entity_field_manager
  ) {
    parent::__construct($config_factory, $state);
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->entityTypeManager = $type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundle = $type_bundle;
    $this->customSettings = [];
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
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
   *
   */
  protected function redirectUser(string $message) {
    drupal_set_message($message, 'error');
    return new RedirectResponse(\Drupal::url('purest.config'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purest_content_settings_form';
  }

  public function getTitle() {
    $type = \Drupal::routeMatch()->getParameter('type');
    $bundle = \Drupal::routeMatch()->getParameter('bundle');
    $field = \Drupal::routeMatch()->getParameter('field');
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($type, $bundle);

    if (isset($bundle_fields[$field])) {
      return $bundle_fields[$field]->getLabel() . ' [' . $bundle_fields[$field]->getName() . '] Field Settings';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = NULL, $bundle = NULL, $field = NULL) {

    // @Todo Allow any custom entity type created by contrib and custom
    // modules.
    // Validate that a valid entity type, bundle and field type are being
    // requested.

    // Redirect to main settings page if any variable doesn't exist.
    if (!$type || !$bundle || !$field) {
      return $this->redirectUser(t('Entity type, variant, or field not supplied.'));
    }

    $entityType = $this->entityTypeBundle->getBundleInfo($type);

    // Load the entity "bundle".
    // switch ($type) {
    //   case 'node':
    //   case 'taxonomy_term':
    //     $entityType = $this
    //       ->entityTypeBundle->getBundleInfo($type);
    //     break;

    //   default:
    //     // redirect if not node or taxonomy term
    //     return $this->redirectUser(t('That entity type does not exist. All available configurable entity types are listed in the table below.'));
    // }

    // If bundle type does not exist redirect to main settings form.
    if (!in_array($bundle, array_keys($entityType))) {
      return $this->redirectUser(t('That entity type does not exist. All available configurable entity types are listed in the table below.'));
    }

    // Load bundle fields.
    $fields = $this->entityFieldManager->getFieldDefinitions($type, $bundle);

    // If field definition does not exist redirect to the main settings form.
    if (!isset($fields[$field])) {
      return $this->redirectUser(t('Entity field does not exist. All available configurable entities are listed in the table below.'));
    }

    // If field is custom check it's an allowed type.
    if ($fields[$field] instanceof FieldDefinitionInterface) {
      $field_type = $fields[$field]->getType();
      if (!in_array($field_type, [
        'image',
      ])) {
        return $this->redirectUser(t('That field is not an image field.'));
      }
    }

    // If field is a base field check it's an allowed type.
    if ($fields[$field] instanceof BaseFieldDefinition) {
      $field_type = $fields[$field]->getType();
      if (!in_array($field_type, ['image',])) {
        return $this->redirectUser(t('That field is not an image field.'));
      }
    }

    if (!isset($field_type)) {
      return $this
        ->redirectUser(t('That field type was not found.'));
    }

    $this->type = $type;
    $this->bundle = $bundle;

    $config = $this
      ->configFactory->get('purest.normalizer.' . $type . '.' . $bundle);
    $settings = $config->get('fields');

    if (NULL === $settings) {
      $settings = [];
    }

    if (!isset($settings[$field])) {
      $settings[$field] = [];
    }

    $form['label'] = [
      '#type' => 'hidden',
      '#value' => $fields[$field]->getName(),
    ];

    $form['type'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#size' => 30,
      '#title' => $this
        ->t('Field Type'),
      '#default_value' => $fields[$field]->getType(),
    ];

    $form['custom_label'] = [
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this
        ->t('Custom Label'),
      '#default_value' => isset($settings[$field]['custom_label']) ? $settings[$field]['custom_label'] : '',
    ];

    $form['hide_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Exclude Field if Empty'),
      '#default_value' => isset($settings[$field]['hide_empty']) ? $settings[$field]['hide_empty'] : 0,
    ];

    $form['exclude'] = [
      '#type' => 'checkbox',
      '#title' => t('Exclude Field From Rest Responses'),
      '#default_value' => isset($settings[$field]['exclude']) ? $settings[$field]['exclude'] : 0,
    ];

    $format = 'meta_multiple_meta';

    if (isset($settings[$field]['format']) && !empty($settings[$field]['format'])) {
      $format = $settings[$field]['format'];
    }

    $form['format'] = [
      '#type' => 'radios',
      '#title' => $this
          ->t('Image Output'),
      '#description' => $this
          ->t('Click below to view example JSON output'),
      '#options' => [
        'meta_multiple_meta' => $this
          ->t('Image metadata and multiple image styles with dimensions'),
        'meta_single_meta' => $this
          ->t('Image metadata and single image style with dimensions'),
        'meta_multiple' => $this
          ->t('Image metadata and multiple image styles'),
        'meta_single' => $this
          ->t('Image metadata and single image'),
        'multiple_meta' => $this
          ->t('Multiple image styles with dimensions'),
        'single_meta' => $this
          ->t('Single image style with dimensions'),
        'multiple' => $this
          ->t('Multiple image styles'),
        'single' => $this
          ->t('Single image style'),
      ],
      '#ajax' => [
        'callback' => [$this, 'updateExampleJson'],
        'event' => 'change',
        'wrapper' => 'ajax-group',
      ],
      '#default_value' => $format,
    ];

    // Container to hold all form elements that might need to update
    // depending on the value of the selected format.
    $form['ajax_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['ajax-group'],
      ],
    ];

    $form['ajax_group']['details'] = [
      '#type' => 'details',
      '#title' => $this
        ->t('Example JSON Response'),
    ];


    $form['ajax_group']['details']['example_json'] = [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => $this->exampleJson($format),
    ];

    // Get available image styles and create options array
    // for checkboxes to exclude styles from responses.
    $styles = ImageStyle::loadMultiple();
    $style_options = [
      'original' => $this
        ->t('Original'),
    ];

    foreach ($styles as $key => $style) {
      $style_options[$key] = $style->getName();
    }

    // Remove any non existent image styles from the config value.
    if (isset($settings[$field]['styles'])) {
      foreach ($settings[$field]['styles'] as $key => $style) {
        if (!isset($styles[$key])) {
          unset($settings[$field]['styles'][$key]);

          // If style is set and matches the non existent style set it to
          // 'original'.
          if (isset($settings[$field]['style']) && $settings[$field]['style'] === $key) {
            $settings[$field]['style'] = 'original';
          }
        }
      }
    }

    $form['ajax_group']['styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this
        ->t('Excluded Image Styles'),
      '#description' => $this
        ->t('Image styles that should be excluded'),
      '#options' => $style_options,
      '#default_value' => isset($settings[$field]['styles']) ? $settings[$field]['styles'] : [],
    ];

    $form['ajax_group']['style'] = [
      '#type' => 'radios',
      '#title' => $this
        ->t('Image Style to Include'),
      '#description' => $this
        ->t('Image style that should be used'),
      '#options' => $style_options,
      '#default_value' => $settings[$field]['style'],
    ];

    switch ($format) {
      case 'meta_multiple_meta':
      case 'meta_multiple':
      case 'multiple_meta':
      case 'multiple':
        $form['ajax_group']['style']['#prefix'] = '<div class="visually-hidden">';
        $form['ajax_group']['style']['#suffix'] = '</div>';
        break;

      default:
        $form['ajax_group']['styles']['#prefix'] = '<div class="visually-hidden">';
        $form['ajax_group']['styles']['#suffix'] = '</div>';
    }

    $form['ajax_group']['fields'] = [
      '#title' => $this
        ->t('Image Object Fields'),
      '#type' => 'table',
      '#sticky' => TRUE,
      '#prefix' => '<h3>' . t('Image Object Properties') . '</h3>',
      '#header' => [
        $this->t('Name'),
        $this->t('Label'),
        $this->t('Type'),
        $this->t('Custom Label'),
        $this->t('Hide if Empty'),
        $this->t('Exclude'),
      ],
    ];

    $psuedo_fields = [
      'fid' => [
        'name' => 'File ID',
        'type' => 'string',
      ],
      'uuid' => [
        'name' => 'UUID',
        'type' => 'string',
      ],
      'alt' => [
        'name' => $this->t('Alternative Text'),
        'type' => 'string',
      ],
      'title' => [
        'name' => $this->t('Title'),
        'type' => 'string',
      ],
      'styles' => [
        'name' => $this->t('Array of image styles'),
        'type' => 'array',
      ],
      'url' => [
        'name' => $this->t('URL of an image style'),
        'type' => 'string',
      ],
      'mime_type' => [
        'name' => $this->t('Mime Type'),
        'type' => 'string',
      ],
      'width' => [
        'name' => $this->t('Width of an image style'),
        'type' => 'integer',
      ],
      'height' => [
        'name' => $this->t('Height of an image style'),
        'type' => 'integer',
      ],
      'original' => [
        'name' => $this->t('Key for the original image style'),
        'type' => 'string',
      ],
    ];

    if (isset($settings[$field]['fields'])) {
      $settings[$field]['fields'] = [];
    }

    foreach ($psuedo_fields as $key => $value) {
      if (isset($settings[$field]['fields'][$key])) {
        $settings[$field]['fields'][$key] = [];
      }

      $form['ajax_group']['fields'][$key]['name'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $value['name'],
      ];

      $form['ajax_group']['fields'][$key]['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $key,
      ];

      $form['ajax_group']['fields'][$key]['type'] = [
        '#type' => 'textfield',
        '#disabled' => TRUE,
        '#size' => 20,
        '#default_value' => $value['type'],
      ];

      $form['ajax_group']['fields'][$key]['custom_label'] = [
        '#type' => 'textfield',
        '#size' => 20,
        '#default_value' => isset($settings[$field]['fields'][$key]['custom_label']) ? $settings[$field]['fields'][$key]['custom_label'] : '',
      ];

      $form['ajax_group']['fields'][$key]['hide_empty'] = [
        '#type' => 'checkbox',
        '#default_value' => isset($settings[$field]['fields'][$key]['hide_empty']) ? $settings[$field]['fields'][$key]['hide_empty'] : 0,
      ];

      $form['ajax_group']['fields'][$key]['exclude'] = [
        '#type' => 'checkbox',
        '#default_value' => isset($settings[$field]['fields'][$key]['exclude']) ? $settings[$field]['fields'][$key]['exclude'] : 0,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function updateExampleJson(array $form, FormStateInterface $form_state) : array {
    $format = $form_state->getValue('format');

    $form['ajax_group']['details']['example_json']['#value'] = '<code>' . $this
      ->exampleJson($format) . '</code>';

    switch ($format) {
      case 'meta_multiple_meta':
      case 'meta_multiple':
      case 'multiple_meta':
      case 'multiple':
        $form['ajax_group']['styles']['#prefix'] = '';
        $form['ajax_group']['styles']['#suffix'] = '';
        $form['ajax_group']['style']['#prefix'] = '<div class="visually-hidden">';
        $form['ajax_group']['style']['#suffix'] = '</div>';
        break;

      default:
        $form['ajax_group']['style']['#prefix'] = '';
        $form['ajax_group']['style']['#suffix'] = '';
        $form['ajax_group']['styles']['#prefix'] = '<div class="visually-hidden">';
        $form['ajax_group']['styles']['#suffix'] = '</div>';
    }

    return $form['ajax_group'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory
      ->getEditable('purest.normalizer.' . $this->type . '.' . $this->bundle);
    $fields = $config->get('fields');

    // The config may never has been saved. If not fields should be empty array.
    if (NULL === $fields) {
      $fields = [];
    }

    $field = [
      'type' => $form_state->getValue('type'),
      'custom_label' => $form_state->getValue('custom_label'),
      'hide_empty' => $form_state->getValue('hide_empty'),
      'exclude' => $form_state->getValue('exclude'),
      'format' => $form_state->getValue('format'),
      'styles' => $form_state->getValue('styles'),
      'style' => $form_state->getValue('style'),
      'fields' => $form_state->getValue('fields'),
    ];

    $fields[$form_state->getValue('label')] = $field;
    $config->set('fields', $fields);
    $config->save();
  }

  /**
   * Array of example JSON structures
   *
   * @return string
   */
  public function exampleJson(string $key) {
    $output = '<code>';

    switch ($key) {
      case 'meta_multiple_meta':
        $output .= '
"image_field_key": {
  "uuid": "d298ccaf-84d9-4628-a696-282ee0c6f32b",
  "alt": "alternate text",
  "title": "title text",
  "mime": "image/jpeg"
  "styles": {
    "original": {
        "url": "https://purest.rocks/sites/default/files/2018-09/test-image.jpg",
        "width": 3000,
        "height": 2000
    },
    "large": {
        "url": "https://purest.rocks/sites/default/files/styles/large/public/2018-09/test-image.jpg?itok=DiXCaFlI",
        "height": 320,
        "width": 480
    },
    "medium": {
        "url": "https://purest.rocks/sites/default/files/styles/medium/public/2018-09/test-image.jpg?itok=TR3wcf88",
        "height": 147,
        "width": 220
    },
    "thumbnail": {
        "url": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO",
        "height": 67,
        "width": 100
    }
  }
}';
        break;

      case 'meta_single_meta':
        $output .= '
"image_field_key": {
  "uuid": "d298ccaf-84d9-4628-a696-282ee0c6f32b",
  "alt": "alternate text",
  "title": "title text",
  "mime": "image/jpeg",
  "url": "https://purest.rocks/sites/default/files/2018-09/test-image.jpg",
  "width": 3000,
  "height": 2000
}';
        break;

      case 'meta_multiple':
        $output .= '
"image_field_key": {
  "uuid": "d298ccaf-84d9-4628-a696-282ee0c6f32b",
  "alt": "alternate text",
  "title": "title text",
  "mime": "image/jpeg",
  "styles": {
    "original": "https://purest.rocks/sites/default/files/2018-09/test-image.jpg",
    "large": "https://purest.rocks/sites/default/files/styles/large/public/2018-09/test-image.jpg?itok=DiXCaFlI",
    "medium": "https://purest.rocks/sites/default/files/styles/medium/public/2018-09/test-image.jpg?itok=TR3wcf88",
    "thumbnail": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO",
  }
}';
        break;

      case 'meta_single':
        $output .= '
"image_field_key": {
  "uuid": "d298ccaf-84d9-4628-a696-282ee0c6f32b",
  "alt": "alternate text",
  "title": "title text",
  "mime": "image/jpeg",
  "url": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO",
}';
        break;

      case 'multiple_meta':
        $output .= '
"image_field_key": {
  "original": {
    "url": "https://purest.rocks/sites/default/files/2018-09/test-image.jpg",
    "width": 3000,
    "height": 2000,
  },
  "large": {
    "url": "https://purest.rocks/sites/default/files/styles/large/public/2018-09/test-image.jpg?itok=DiXCaFlI",
    "height": 320,
    "width": 480,
  },
  "medium": {
    "url": "https://purest.rocks/sites/default/files/styles/medium/public/2018-09/test-image.jpg?itok=TR3wcf88",
    "height": 147,
    "width": 220,
  },
  "thumbnail": {
    "url": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO",
    "height": 67,
    "width": 100,
  }
}';
        break;

      case 'single_meta':
        $output .= '
"image_field_key": {
  "url": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO",
  "height": 67,
  "width": 100,
}';
        break;

      case 'multiple':
        $output .= '
"image_field_key": {
  "original": "https://purest.rocks/sites/default/files/2018-09/test-image.jpg",
  "large": "https://purest.rocks/sites/default/files/styles/large/public/2018-09/test-image.jpg?itok=DiXCaFlI",
  "medium": "https://purest.rocks/sites/default/files/styles/medium/public/2018-09/test-image.jpg?itok=TR3wcf88",
  "thumbnail": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO",
}';
        break;

      case 'single':
        $output .= '
"image_field_key": "https://purest.rocks/sites/default/files/styles/thumbnail/public/2018-09/test-image.jpg?itok=YlkT07eO"';
      break;
    }

    $output .= '</code>';
    return $output;
  }
}
