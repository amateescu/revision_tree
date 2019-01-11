<?php

namespace Drupal\revision_tree\Context;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\revision_tree\Plugin\Context\RevisionNegotiationContextInterface;

/**
 * Implementation of the RevisionNegotiationContextDiscoveryInterface.
 */
class RevisionNegotiationContextDiscovery implements RevisionNegotiationContextDiscoveryInterface {

  /**
   * The list of context providers.
   *
   * @var \Drupal\Core\Plugin\Context\ContextProviderInterface[]
   */
  protected $contextProviders = [];

  /**
   * A cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * RevisionNegotiationContextDiscovery constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend to cache lookups in.
   */
  public function __construct(CacheBackendInterface $cacheBackend) {
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * @inheritdoc
   */
  public function getEntityContextDefinitions(EntityTypeInterface $entityType) {
    $cid = 'revision_negotiation_contexts:' . $entityType->id();

    if ($cache = $this->cacheBackend->get($cid)) {
      return $cache->data;
    }

    $definitions = [];
    foreach ($this->contextProviders as $contextProvider) {
      foreach ($contextProvider->getAvailableContexts() as $id => $context) {
        if ($context instanceof RevisionNegotiationContextInterface && $context->applies($entityType)) {
          $definitions[$id] = [
            'field' => $context->getContextField(),
            'weight' => $context->getWeight(),
          ];
        }
      }
    }

    $this->cacheBackend->set($cid, $definitions, Cache::PERMANENT, [
      // TODO: Do we need cache tags to invalidate this?
    ]);

    return $definitions;
  }

  /**
   * Add a context to the list.
   *
   * Used as a service collector method.
   *
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $contextProvider
   *   The context provider.
   */
  public function addContextProvider(ContextProviderInterface $contextProvider) {
    $this->contextProviders[] = $contextProvider;
  }

}
