<?php

namespace Drupal\hts_facets\Plugin\facets\widget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The HTS hierarchical widget.
 *
 * Renders facet results as a nested, cascading taxonomy selector. When the
 * facet has hierarchy enabled (Facets' HierarchyProcessor), top-level results
 * carry their descendants via getChildren(); this widget recurses over those
 * children to produce nested item lists. Without hierarchy the children are
 * empty, so the widget degrades to a flat list.
 *
 * The build only uses ResultInterface methods that are identical in Facets 2
 * and 3, so no version-specific handling is required.
 *
 * @FacetsWidget(
 *   id = "hts_hierarchical",
 *   label = @Translation("HTS hierarchical"),
 *   description = @Translation("Displays facet results as a hierarchical cascading taxonomy selector."),
 * )
 */
class HtsHierarchicalWidget extends WidgetPluginBase {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The facet widget render array.
   */
  public function build(FacetInterface $facet) {
    $this->facet = $facet;

    $items = [];
    foreach ($facet->getResults() as $result) {
      $items[] = $this->buildResult($result);
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'data-drupal-facet-id' => $facet->id(),
        'data-drupal-facet-alias' => $facet->getUrlAlias(),
        'class' => [
          'hts-facet',
          $facet->getActiveItems() ? 'facet-active' : 'facet-inactive',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
      ],
    ];
  }

  /**
   * Builds a single result as a nested item-list item.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result to render.
   *
   * @return array<string, mixed>
   *   A render array suitable for an item_list #items entry.
   */
  protected function buildResult(ResultInterface $result) {
    $classes = ['facet-item'];
    $item = $this->buildLink($result);

    $children = $result->getChildren();
    if ($children) {
      $classes[] = 'facet-item--has-children';
      $child_items = [];
      foreach ($children as $child) {
        $child_items[] = $this->buildResult($child);
      }
      $item['children'] = [
        '#theme' => 'item_list',
        '#items' => $child_items,
      ];
      if ($result->hasActiveChildren()) {
        $classes[] = 'facet-item--active-trail';
      }
    }

    if ($result->isActive()) {
      $item['#attributes']['class'][] = 'is-active';
    }

    $item['#wrapper_attributes'] = ['class' => $classes];
    $item['#attributes']['data-drupal-facet-item-id'] = Html::getClass(
      $this->facet->getUrlAlias() . '-'
      . strtr($result->getRawValue(), ' \'"', '---')
    );
    $item['#attributes']['data-drupal-facet-item-value'] = $result->getRawValue();
    $item['#attributes']['data-drupal-facet-item-count'] = $result->getCount();

    return $item;
  }

  /**
   * Builds the link or plain-text label for a result.
   *
   * Results without a URL render as plain text, mirroring the empty-URL
   * handling in Facets' own LinksWidget.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result to render.
   *
   * @return array<string, mixed>
   *   The result label as a render array.
   */
  protected function buildLink(ResultInterface $result) {
    $label = [
      '#theme' => 'facets_result_item',
      '#is_active' => $result->isActive(),
      '#value' => $result->getDisplayValue(),
      '#show_count' => (bool) $this->getConfiguration()['show_numbers'],
      '#count' => $result->getCount(),
      '#facet' => $result->getFacet(),
      '#raw_value' => $result->getRawValue(),
    ];

    // Results without a URL render as plain text, mirroring the empty-URL
    // handling in Facets' own WidgetPluginBase::build(). The interface types
    // getUrl() as non-null, but the underlying property is unset until a URL
    // is assigned, so it is genuinely nullable at runtime.
    /** @var \Drupal\Core\Url|null $url */
    $url = $result->getUrl();
    if ($url === NULL) {
      return $label;
    }

    return (new Link($label, $url))->toRenderable();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The default widget configuration.
   */
  public function defaultConfiguration() {
    return ['show_numbers' => FALSE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet being configured.
   *
   * @return array<string, mixed>
   *   The configuration form array.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);
    $form['show_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the amount of results'),
      '#default_value' => $this->getConfiguration()['show_numbers'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    return TRUE;
  }

}
