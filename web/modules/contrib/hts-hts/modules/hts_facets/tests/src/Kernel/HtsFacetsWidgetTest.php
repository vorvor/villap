<?php

namespace Drupal\Tests\hts_facets\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Result\Result;
use Drupal\facets\Widget\WidgetPluginInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel coverage for the optional HTS hierarchical Facets widget.
 *
 * Verifies the widget renders facet results as a nested item list when the
 * facet exposes hierarchy (children), degrades to a flat list otherwise, and
 * toggles counts and active-state classes according to configuration.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
class HtsFacetsWidgetTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'taxonomy',
    'search_api',
    'facets',
    'hts',
    'hts_facets',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    // A normal account subject to access checks, not the superuser.
    $this->createUser();
    $this->setCurrentUser($this->createUser(['access content']));
  }

  /**
   * Creates the widget instance through the Facets widget plugin manager.
   *
   * @param array $config
   *   The widget configuration.
   *
   * @return \Drupal\facets\Widget\WidgetPluginInterface
   *   The widget plugin.
   */
  private function widget(array $config = []): WidgetPluginInterface {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.facets.widget');
    $widget = $manager->createInstance('hts_hierarchical', $config);
    $this->assertInstanceOf(WidgetPluginInterface::class, $widget);
    return $widget;
  }

  /**
   * Builds a facet carrying the supplied results.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $results
   *   The results to attach.
   *
   * @return \Drupal\facets\Entity\Facet
   *   The facet.
   */
  private function facet(array $results): Facet {
    $facet = new Facet(['id' => 'test_facet', 'url_alias' => 'test'], 'facets_facet');
    $facet->setResults($results);
    return $facet;
  }

  /**
   * Tests the default configuration and query type.
   */
  public function testDefaults(): void {
    $widget = $this->widget();
    $this->assertSame(['show_numbers' => FALSE], $widget->defaultConfiguration());
    $this->assertNull($widget->getQueryType());
    $facet = $this->facet([]);
    $this->assertTrue($widget->supportsFacet($facet));
  }

  /**
   * Tests nested rendering when results carry children.
   */
  public function testHierarchicalBuild(): void {
    $widget = $this->widget(['show_numbers' => TRUE]);

    $facet = $this->facet([]);
    $parent = new Result($facet, 'animals', 'Animals', 30);
    $parent->setUrl(new Url('<front>'));
    $child = new Result($facet, 'llama', 'Llama', 10);
    $child->setUrl(new Url('<front>'));
    $child->setActiveState(TRUE);
    $parent->setChildren([$child]);
    $facet->setResults([$parent]);

    $build = $widget->build($facet);

    $this->assertSame('item_list', $build['#theme']);
    $this->assertCount(1, $build['#items']);

    $parent_item = $build['#items'][0];
    // The parent flags it has children and an active trail below it.
    $this->assertContains('facet-item--has-children', $parent_item['#wrapper_attributes']['class']);
    $this->assertContains('facet-item--active-trail', $parent_item['#wrapper_attributes']['class']);

    // The nested child list is present under the parent.
    $this->assertArrayHasKey('children', $parent_item);
    $this->assertSame('item_list', $parent_item['children']['#theme']);
    $this->assertCount(1, $parent_item['children']['#items']);

    $child_item = $parent_item['children']['#items'][0];
    // The active child carries the is-active class.
    $this->assertContains('is-active', $child_item['#attributes']['class']);

    // Counts are shown because show_numbers is on.
    $this->assertTrue($parent_item['#title']['#show_count']);
    $this->assertSame(30, $parent_item['#title']['#count']);
    $this->assertSame(10, $child_item['#title']['#count']);
  }

  /**
   * Tests flat rendering when no result has children.
   */
  public function testFlatBuild(): void {
    $widget = $this->widget();

    $facet = $this->facet([]);
    $one = new Result($facet, 'llama', 'Llama', 10);
    $one->setUrl(new Url('<front>'));
    $two = new Result($facet, 'badger', 'Badger', 20);
    $two->setUrl(new Url('<front>'));
    $facet->setResults([$one, $two]);

    $build = $widget->build($facet);

    $this->assertCount(2, $build['#items']);
    foreach ($build['#items'] as $item) {
      // No children, so no nested list and no expansion class.
      $this->assertArrayNotHasKey('children', $item);
      $this->assertSame(['facet-item'], $item['#wrapper_attributes']['class']);
      // show_numbers defaults to FALSE, so counts stay hidden.
      $this->assertFalse($item['#title']['#show_count']);
    }
  }

  /**
   * Tests that a result without a URL renders as plain text, not a link.
   */
  public function testResultWithoutUrl(): void {
    $widget = $this->widget();

    $facet = $this->facet([]);
    $result = new Result($facet, 'llama', 'Llama', 10);
    $facet->setResults([$result]);

    $build = $widget->build($facet);

    $item = $build['#items'][0];
    // Plain-text results expose the facets_result_item theme directly rather
    // than a wrapping link render array.
    $this->assertSame('facets_result_item', $item['#theme']);
    $this->assertArrayNotHasKey('#type', $item);
  }

}
