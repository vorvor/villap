<?php

namespace Drupal\hts_search_api\Plugin\views\filter;

use Drupal\hts\HtsTaxonomyFilterTrait;
use Drupal\search_api\Plugin\views\filter\SearchApiTerm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the HTS cascading term filter for Search API Views.
 *
 * Like its SQL sibling {@see HtsTaxonomyIndexTid}, this filter renders the HTS
 * cascading exposed selector and resolves the chosen path to a concrete set of
 * term ids in $this->value. It differs only in how that value reaches the
 * query: the parent {@see SearchApiTerm} pulls in SearchApiFilterTrait, whose
 * opHelper() applies $this->value as a Search API "IN" condition on the index
 * field instead of building an SQL join.
 *
 * HTS already expands the deepest selection to its whole subtree in
 * applyIdentifierInput(), so the IN condition over the subtree ids matches
 * descendants WITHOUT requiring the Search API "Add hierarchy" processor on the
 * index.
 *
 * SearchApiFilterTrait supplies valueForm(), opSimple() and opHelper(), while
 * HtsTaxonomyFilterTrait only reads/sets $this->value through
 * acceptExposedInput()/applyIdentifierInput() and never defines those three
 * methods, so the two traits compose without any method conflict.
 *
 * This class carries no plugin annotation or attribute on purpose: like
 * SearchApiTerm it is registered dynamically in
 * hts_search_api_views_plugins_filter_alter() so nothing autoloads when the
 * Search API module is absent.
 *
 * @ingroup views_filter_handlers
 *
 * @see hts_search_api_views_plugins_filter_alter()
 */
class HtsSearchApiTerm extends SearchApiTerm {

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
