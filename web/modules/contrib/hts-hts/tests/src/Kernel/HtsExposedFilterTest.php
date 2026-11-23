<?php

declare(strict_types=1);

namespace Drupal\Tests\hts\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\RoleInterface;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the HTS exposed Views filter narrows results by the deepest term.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
final class HtsExposedFilterTest extends KernelTestBase {

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
    'node',
    'views',
    'hts',
  ];

  /**
   * Term ids keyed by name.
   *
   * @var array<string, int>
   */
  private array $tids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user', 'field', 'filter', 'node', 'taxonomy']);

    // The exposed filter resolves term labels through entity access, mirroring
    // an anonymous site visitor. Grant the base content permission so the
    // resolver keeps the accessible terms instead of discarding them.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);

    Vocabulary::create(['vid' => 'hts_test', 'name' => 'HTS Test'])->save();
    $make = function (string $name, int $parent = 0): int {
      $term = Term::create(['vid' => 'hts_test', 'name' => $name, 'parent' => [$parent]]);
      $term->save();
      return (int) $term->id();
    };
    $this->tids['europe'] = $make('Europe');
    $this->tids['spain'] = $make('Spain', $this->tids['europe']);
    $this->tids['madrid'] = $make('Madrid', $this->tids['spain']);
    $this->tids['france'] = $make('France', $this->tids['europe']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_hts_terms',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_hts_terms',
      'entity_type' => 'node',
      'bundle' => 'article',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['hts_test' => 'hts_test']],
      ],
    ])->save();

    $this->createNode('Node Madrid', $this->tids['madrid']);
    $this->createNode('Node France', $this->tids['france']);

    $this->createView();
  }

  /**
   * Drilling to a leaf resolves the filter to that leaf only.
   */
  public function testDrillToLeafNarrowsToLeaf(): void {
    $value = $this->resolveFilter([
      '0' => (string) $this->tids['europe'],
      '1' => (string) $this->tids['spain'],
      '2' => (string) $this->tids['madrid'],
    ]);
    $this->assertSame([$this->tids['madrid']], $value);
  }

  /**
   * Drilling down a sibling branch resolves to that branch only.
   */
  public function testDrillToSiblingBranch(): void {
    $value = $this->resolveFilter([
      '0' => (string) $this->tids['europe'],
      '1' => (string) $this->tids['france'],
    ]);
    $this->assertSame([$this->tids['france']], $value);
  }

  /**
   * Selecting a mid-level term includes that term and its descendants.
   */
  public function testMidLevelIncludesSubtree(): void {
    $value = $this->resolveFilter([
      '0' => (string) $this->tids['europe'],
      '1' => (string) $this->tids['spain'],
    ]);
    $expected = [$this->tids['spain'], $this->tids['madrid']];
    sort($expected);
    $this->assertSame($expected, $value);
  }

  /**
   * Selecting the root includes its whole subtree.
   */
  public function testAncestorIncludesSubtree(): void {
    $value = $this->resolveFilter(['0' => (string) $this->tids['europe']]);
    $expected = array_values($this->tids);
    sort($expected);
    $this->assertSame($expected, $value);
  }

  /**
   * No selection resolves to no filter value.
   */
  public function testNoSelectionDoesNotFilter(): void {
    $this->assertSame([], $this->resolveFilter([]));
  }

  /**
   * Stale autocomplete values do not override an explicitly reset cascade.
   */
  public function testCascadeResetIgnoresStaleAutocomplete(): void {
    $view = Views::getView('hts_test_view');
    $view->setDisplay('page_1');
    $view->initHandlers();
    $filter = $view->filter['field_hts_terms_target_id'];
    $filter->acceptExposedInput([
      'field_hts_terms' => [
        'target_id' => 'Madrid (' . $this->tids['madrid'] . ')',
        'hierarchical_term_selector' => [
          'elements' => [
            '0' => '_none',
            '1' => '_none',
            '2' => '_none',
          ],
        ],
      ],
    ]);

    $this->assertSame([], array_values((array) $filter->value));
    $view->destroy();
  }

  /**
   * Resolves the exposed HTS filter value for a cascade selection.
   *
   * @param array<string, string> $elements
   *   The per-level selected term ids.
   *
   * @return int[]
   *   The sorted term ids the filter would query.
   */
  private function resolveFilter(array $elements): array {
    $view = Views::getView('hts_test_view');
    $view->setDisplay('page_1');
    $view->initHandlers();
    $filter = $view->filter['field_hts_terms_target_id'];
    $filter->acceptExposedInput([
      'field_hts_terms' => [
        'target_id' => '',
        'hierarchical_term_selector' => ['elements' => $elements],
      ],
    ]);
    $value = array_map('intval', array_values((array) $filter->value));
    $view->destroy();
    sort($value);
    return $value;
  }

  /**
   * Creates a test node referencing a term.
   */
  private function createNode(string $title, int $tid): void {
    Node::create([
      'type' => 'article',
      'title' => $title,
      'field_hts_terms' => [$tid],
      'status' => 1,
    ])->save();
  }

  /**
   * Creates the view exercised by the tests.
   */
  private function createView(): void {
    View::create([
      'id' => 'hts_test_view',
      'label' => 'HTS Test View',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
                'plugin_id' => 'field',
                'entity_type' => 'node',
                'entity_field' => 'title',
              ],
            ],
            'filters' => [
              'field_hts_terms_target_id' => [
                'id' => 'field_hts_terms_target_id',
                'table' => 'node__field_hts_terms',
                'field' => 'field_hts_terms_target_id',
                'plugin_id' => 'hts_taxonomy_index_tid',
                'entity_type' => 'node',
                'exposed' => TRUE,
                'expose' => [
                  'operator_id' => 'field_hts_terms_target_id_op',
                  'label' => 'HTS Terms',
                  'identifier' => 'field_hts_terms',
                  'multiple' => TRUE,
                ],
                'type' => 'hts',
                'vid' => 'hts_test',
                'limit' => TRUE,
                'hierarchical_term_selector' => [
                  'hierarchy_depth' => 3,
                  'widget_type' => 'select',
                  'label_hide' => FALSE,
                  'multiple_parent_policy' => 'first',
                ],
              ],
            ],
            'pager' => ['type' => 'none'],
            'row' => ['type' => 'fields'],
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_options' => ['path' => 'hts-test-view'],
        ],
      ],
    ])->save();
  }

}
