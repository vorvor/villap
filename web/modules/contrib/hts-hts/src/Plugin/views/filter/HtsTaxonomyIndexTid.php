<?php

namespace Drupal\hts\Plugin\views\filter;

use Drupal\hts\HtsTaxonomyFilterTrait;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\views\Attribute\ViewsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the HTS taxonomy-index Views filter.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('hts_taxonomy_index_tid')]
class HtsTaxonomyIndexTid extends TaxonomyIndexTid {

  use HtsTaxonomyFilterTrait;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->htsAssignFilterServices($container);
    return $instance;
  }

}
