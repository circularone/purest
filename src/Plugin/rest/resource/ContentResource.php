<?php

namespace Drupal\purest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\Core\Path\AliasStorageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Url;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Path\AliasManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides a resource to get entities of type node and taxonomy
 * term by path alias.
 *
 * @RestResource(
 *   id = "purest_content_resource",
 *   label = @Translation("Purest Content Resource"),
 *   uri_paths = {
 *     "canonical" = "/purest/content"
 *   }
 * )
 */
class ContentResource extends ResourceBase {

  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * AliasStorageInterface.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorageInterface;

  /**
   * LanguageManager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * AliasManager.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The configured site languages.
   *
   * @var array
   */
  protected $languages;

  /**
   * The language id.
   *
   * @var int
   */
  protected $language;

  /**
   * The request alias.
   *
   * @var string
   */
  protected $requestAlias;

  /**
   * The request language id.
   *
   * @var string
   */
  protected $requestLanguage;


  /**
   * Constructs a new ProductResource object.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, Request $current_request, AliasStorageInterface $alias_storage_interface, LanguageManager $language_manager, AliasManager $alias_manager, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->entityTypeManager = $entity_type_manager;
    $this->currentRequest = $current_request;
    $this->aliasStorageInterface = $alias_storage_interface;
    $this->languageManager = $language_manager;
    $this->aliasManager = $alias_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->languages = $this->languageManager->getLanguages();
    $this->language = $this->languageManager->getCurrentLanguage()->getId();
    $this->requestAlias = $this->currentRequest->query->get('alias') ?? '/';
    $this->requestLanguage = $this->currentRequest->query->get('language');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('path.alias_storage'),
      $container->get('language_manager'),
      $container->get('path.alias_manager'),
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    $url_params = $this->requestLanguage ? [
      'language' => $this->requestLanguage
    ] : [];

    $cache_contexts = ['url.query_args:alias', 'url.query_args:language'];

    if ($this->requestLanguage) {
      if (!array_key_exists($this->requestLanguage, $this->languages)) {
        return $this->returnResponse(406, $url_params, $cache_dependencies);
      }
    }

    $cache_dependencies = [(
      new CacheableMetadata())->addCacheContexts($cache_contexts
    )];

    $path = $this->aliasManager
      ->getPathByAlias($this->requestAlias, $this->requestLanguage ?? NULL);

    // Check if alias has corresponding internal path.
    if ($path === $this->requestAlias) {
      return $this->returnResponse(404, $url_params, $cache_dependencies);
    }

    // Load the entity by internal path.
    $url = Url::fromUri('internal:' . $path, $url_params);

    $params = $url->getRouteParameters();
    $entity_type = key($params);
    $this->entityStorage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $this->entityStorage->load($params[$entity_type]);

    if ($this->requestLanguage) {
      $entity = $entity->getTranslation($this->requestLanguage);
    }

    // Add entity as cacheable dependency.
    $cache_dependencies[] = $entity;

    // Check user has access to the entity.
    $account = \Drupal::currentUser();
    if (!$check = $entity->access('view', $account)) {
      return $this->returnResponse(403, $url_params, $cache_dependencies);
    }

    // Get purest entity config for caching.
    $config_id = 'purest.normalizer.' . $entity
        ->getEntityTypeId() . '.' . $entity->bundle();
    $entity_config = $this->configFactory->get($config_id);
    if ($entity_config !== NULL) {
      $cache_dependencies[] = $entity_config;
    }

    return $this->returnResponse(200, $url_params, $cache_dependencies, $entity);
  }

  /**
   * Get default site pages.
   *
   * @param integer $code
   *   The response code.
   *
   * @param array $url_params
   *   Url paramaters e.g. language.
   *
   * @param array $cache_dependencies
   *   Cacheable dependencies array.
   *
   * @return Drupal\Core\Entity or FALSE
   */
  public function defaultErrorEntity($code, $url_params, &$cache_dependencies) {
    $config = $this->configFactory->get('system.site');

    if ($path = $config->get('page.' . $code)) {
      $cache_dependencies[] = $config;
      $url = Url::fromUri('internal:' . $path, $url_params);
      $params = $url->getRouteParameters();
      $entity_type = key($params);
      $this->entityStorage = $this->entityTypeManager->getStorage($entity_type);
      return $this->entityStorage->load($params[$entity_type]);
    }

    return FALSE;
  }

  public function returnResponse($code, $url_params, $cache_dependencies = [], $content = NULL) {
    if ($code !== 200) {
      if ($error_entity = $this
        ->defaultErrorEntity($code, $url_params, $cache_dependencies)) {
        $cache_dependencies[] = $error_entity;
        $content = $error_entity;
      }
      else {
        switch ($code) {
          case 404:
            $content = ['error' => $this->t('Not found.')];
            break;
          case 403:
            $content = ['error' => $this->t('Not allowed.')];
            break;
          case 406:
            $content = ['error' => $this->t('Unacceptable.')];
            break;
        }
      }
    }

    $response = new ResourceResponse($content, $code);

    foreach ($cache_dependencies as $dependency) {
      $response->addCacheableDependency($dependency);
    }

    return $response;
  }

}
