<?php

namespace Drupal\ezcontent_preview;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\access_unpublished\AccessTokenManager;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Utility class.
 */
class Utils {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The time service.
   *
   * @var \Drupal\access_unpublished\AccessTokenManager
   */
  protected $accessToken;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, MessengerInterface $messenger, AliasManagerInterface $aliasManager, TimeInterface $time, AccessTokenManager $accessToken) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
    $this->aliasManager = $aliasManager;
    $this->time = $time;
    $this->accessToken = $accessToken;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('path_alias.manager'),
      $container->get('datetime.time'),
      $container->get('access_unpublished.access_token_manager')
    );
  }

  /**
   * Builds preview url on the basis of config and node path alias.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node interface.
   * @param string $decoupledRoutes
   *   The routing config.
   * @param string $options
   *   The config options.
   */
  public function buildUrl(NodeInterface $node, $decoupledRoutes, array $options = []) {

    try {
      $preview_base_url = \Drupal::token()->replace($decoupledRoutes->url, ['node' => $node]);
    }
    catch (\Exception $e) {
      \Drupal::logger('ezcontent_preview')->error($e->getMessage());
      return FALSE;
    }

    if (!$preview_base_url) {
      $this->messenger->addMessage('Add frontend URL in module config form to view decoupled preview.', 'custom');
      return;
    }

    // If node is unpublished using
    // https://www.drupal.org/project/access_unpublished
    // module, then it should generate token and pass it to Drupal.
    if (!$node->isPublished()) {

      $tokenKey = $this->configFactory->get('access_unpublished.settings')
        ->get('hash_key');
      $activeToken = $this->accessToken->getActiveAccessToken($node);
      if (!$activeToken) {
        $activeToken = $this->buildToken($node);
      }
      $tokenValue = $activeToken->get('value')->value;
      $options['query'] = [$tokenKey => $tokenValue];
    }

    $siteUrl = Url::fromUri($preview_base_url, $options);
    return $siteUrl;
  }

  /**
   * Token bulding.
   *
   * @param object $entity
   *   Entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Access Unpublished Token.
   */
  public function buildToken($entity) {
    $tokenKey = $this->configFactory->get('ezcontent_preview.settings')->get('ezcontent_preview_token_expire_time');
    if (!$tokenKey) {
      $tokenKey = 300;
    }
    $access_token = $this->entityTypeManager->getStorage('access_token')->create(
      [
        'entity_type' => $entity->getEntityType()->id(),
        'entity_id' => $entity->id(),
        'expire' => $this->time->getRequestTime() + $tokenKey,
      ]
    );
    $access_token->save();
    return $access_token;
  }

  /**
   * Get list of decoupled entities.
   *
   * @return array
   *   Array of decoupled entities.
   */
  public function getDecoupledEntities() {
    $entities = $this->entityTypeManager->getStorage('ezcontent_preview');
    $query = $entities->getQuery();
    $query->sort('weight', 'ASC');
    $ids = $query->accessCheck(FALSE)->execute();
    $getDecoupledRoutes = $entities->loadMultiple($ids);
    return $getDecoupledRoutes;
  }

  /**
   * Get list of decoupled entities.
   *
   * @return array
   *   Array of decoupled entties with parsed URL for preview panel
   */
  public function getNodeDecoupledRoutes(NodeInterface $node) {

    $user = \Drupal::currentUser();
    if (!$user->hasPermission('EZContent view preview')) {
      return FALSE;
    }

    $nodeTypes = $this->entityTypeManager->getStorage('node_type')->load($node->bundle());

    // If preview is enabled.
    if ($nodeTypes->get('preview_mode')) {
      $utils = \Drupal::service('ezcontent_preview.utils');
      $getDecoupledRoutes = $utils->getDecoupledEntities();
      $decoupledRoutes = [];

      if ($getDecoupledRoutes) {
        foreach ($getDecoupledRoutes as $route) {
          // If the current node is part of selected entties.
          if ($route->content_entity[$node->bundle()]) {
            $parseUrl = $utils->buildUrl($node, $route);
            if ($parseUrl) {
              $decoupledRoutes[] = [
                'label' => $route->label,
                'url' => $parseUrl->toString(),
              ];
            }
          }
        }
      }
      return $decoupledRoutes;
    }

  }

}
