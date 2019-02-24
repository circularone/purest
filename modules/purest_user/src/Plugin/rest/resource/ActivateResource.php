<?php

namespace Drupal\purest_user\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\purest_user\AccountValidationServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "purest_user_activate_resource",
 *   label = @Translation("Purest User Activate"),
 *   uri_paths = {
 *     "canonical" = "/purest/user/activate"
 *   }
 * )
 */
class ActivateResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The account validation service
   */
  protected $accountValidationService;

  /**
   * Entity type manager.
   */
  protected $entityTypeManager;

  /**
   * Constructs a new UserActivationRestResource object.
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
    AccountProxyInterface $current_user,
    AccountValidationServiceInterface $validation_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );

    $this->currentUser = $current_user;
    $this->accountValidationService = $validation_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('purest_user'),
      $container->get('current_user'),
      $container->get('purest_user.validation'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to PATCH requests.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function patch($data) {

    if(!isset($data['id']) || !isset($data['token']) || !isset($data['timestamp'])) {
      throw new BadRequestHttpException(t('ID, token and timestamp must be provided.'));
    }

    $user_storage = $this->entityTypeManager->getStorage('user');
    $user = $user_storage->load($data['id']);

    if (!$user) {
      throw new BadRequestHttpException(t('User does not exist.'));
    }

    // If Purest Recaptcha module is enabled check if it should
    // be used on this resource.
    if (\Drupal::moduleHandler()->moduleExists('purest_recaptcha')) {
      $purest_user_config = $this->configFactory->get('purest_user.settings');
      $resources_recaptcha = $purest_user_config->get('resources_recaptcha');
      $use_recaptcha = $resources_recaptcha['activate'];

      if ($use_recaptcha) {
        $recaptcha_service = \Drupal::service('purest_recaptcha.recaptcha');

        if (!is_string($this->recaptchaResponse)) {
          throw new BadRequestHttpException(t('reCAPTCHA query string must be present.'));
        }

        $recaptcha_valid = $recaptcha_service->validate($this->recaptchaResponse);

        if (!$recaptcha_valid) {
          throw new BadRequestHttpException(t('reCAPTCHA validation failed.'));
        }
      }
    }

    $active = $this->accountValidationService->activateAccount(
      $user,
      $data['token'],
      $data['timestamp']
    );

    if ($active) {
      return new ModifiedResourceResponse($user, 200);
    }
    else {
      throw new BadRequestHttpException(t('Account could not be activated.'));
    }
  }

}
