<?php

namespace Drupal\purest_user\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;

/**
 * Class ConfigForm.
 */
class ConfigForm extends ConfigFormBase {

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
  protected $entityFieldManager;

  /**
   * Constructs a new RecaptchaConfigForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManager $entity_field_manager
  ) {
    parent::__construct($config_factory, $state);
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->recaptcha = \Drupal::moduleHandler()->moduleExists('purest_recaptcha');
    $this->pages = [
      'register' => 'Register',
      'login' => 'Log In',
      'activate' => 'Activate Account',
      'reset' => 'Password Reset',
      'change' => 'Change Password'
    ];
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'purest_user.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purest_user_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('purest_user.settings');
    $this->entityStorage = $this->entityTypeManager->getStorage('node');

    $form['#prefix'] = '<p>' . $this->t('Set nodes or custom paths to use for various user operations. These will be used in user emails to build URLs for account based actions.') . '</p>';

    $form['user_pages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User Action Page Links'),
    ];

    $form['user_pages']['user_actions'] = [
      '#type' => 'horizontal_tabs',
      '#default_tab' => 'edit-register',
    ];

    foreach ($this->pages as $key => $action ) {
      $form['user_pages'][$key] = [
        '#type' => 'details',
        '#title' => $action,
        '#group' => 'information',
      ];

      $form['user_pages'][$key][$key . '_node'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#title' => $this->t('Node'),
        '#description' => $this->t('A node to extract the path from. This will take precedence over any custom path below.'),
      ];

      $node_id = $config->get($key . '_node');
      
      if ($node_id) {
        $entity = $this->entityStorage->load($node_id);
        $form['user_pages'][$key][$key . '_node']['#default_value'] = $entity;
      }
      
      $form['user_pages'][$key][$key . '_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Custom Path'),
        '#description' => $this->t('A custom path for use if no node available.'),
        '#maxlength' => 256,
        '#size' => 64,
        '#default_value' => $config->get($key . '_path'),
      ];
    }

    if ($this->recaptcha) {
      $form['recaptcha'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Rest Resources Recaptcha Settings'),
      ];

      $form['recaptcha']['resources_recaptcha'] = [
        '#type' => 'checkboxes',
        '#options' => $this->pages,
        '#default_value' => $config->get('resources_recaptcha'),
        '#description' => $this->t('Check the resources on which to include recaptcha validation.')
      ];

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory->getEditable('purest_user.settings');
    
    foreach ($this->pages as $key => $action) {
      $config
        ->set($key . '_node', $form_state->getValue($key . '_node'))
        ->set($key . '_path', $form_state->getValue($key . '_path'));
    }

    if ($this->recaptcha) {
      $config->set('resources_recaptcha', $form_state->getValue('resources_recaptcha'));
    }

    $config->save();
  }

}
