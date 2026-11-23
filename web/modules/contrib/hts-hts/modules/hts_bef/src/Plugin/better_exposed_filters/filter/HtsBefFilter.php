<?php

namespace Drupal\hts_bef\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hts\Plugin\views\filter\HtsTaxonomyIndexTid;
use Drupal\hts\Service\HtsBefShared;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Better Exposed Filters widget wrapper for an HTS taxonomy filter.
 *
 * Declared with the annotation discovery mechanism, which the widget plugin
 * manager accepts in both the annotation-based (6.x) and attribute-based (7.x)
 * releases of Better Exposed Filters. The widget explicitly implements
 * ContainerFactoryPluginInterface so the shared service is injected on both
 * releases, whose base constructors have different signatures.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "hts",
 *   label = @Translation("Hierarchical Term Selector"),
 * )
 */
final class HtsBefFilter extends FilterWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * BEF-neutral input helpers.
   *
   * @var \Drupal\hts\Service\HtsBefShared
   */
  protected HtsBefShared $htsBefShared;

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $arguments = [$configuration, $plugin_id, $plugin_definition];
    // The 7.x base constructor additionally requires the current request and
    // the config factory. Detect that arity from the base constructor itself
    // and only supply those services when the installed release expects them.
    $constructor = (new \ReflectionClass(FilterWidgetBase::class))->getConstructor();
    if ($constructor !== NULL && $constructor->getNumberOfParameters() > 3) {
      $arguments[] = $container->get('request_stack')->getCurrentRequest();
      $arguments[] = $container->get('config.factory');
    }

    // Instantiate through reflection so the variable constructor arity detected
    // above is honored without a static call PHPStan cannot verify against the
    // installed base class.
    /** @var static $instance */
    $instance = (new \ReflectionClass(static::class))->newInstanceArgs($arguments);
    $instance->htsBefShared = $container->get('hts.bef_shared');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @param object|null $filter
   *   The Views filter plugin, if any.
   * @param array<string, mixed> $filter_options
   *   Filter options supplied by Better Exposed Filters.
   */
  public static function isApplicable($filter = NULL, array $filter_options = []): bool {
    return $filter instanceof HtsTaxonomyIndexTid
      && ($filter_options['type'] ?? ($filter->options['type'] ?? NULL)) === 'hts';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed, mixed> $form
   *   The exposed form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);
    if (!$this->handler instanceof HtsTaxonomyIndexTid) {
      return;
    }
    $identifier = $this->handler->options['expose']['identifier'] ?? '';
    if ($identifier !== '') {
      $this->htsBefShared->alterExposedForm($form, $form_state, $identifier);
    }
  }

}
