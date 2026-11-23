<?php

declare(strict_types=1);

namespace Drupal\Tests\hts_search_api\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hts_search_api\Plugin\views\filter\HtsSearchApiTerm;
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
 * Verifies the HTS Search API exposed filter narrows an index-backed View.
 *
 * The View's base table is a Search API index (not an SQL table), so the plain
 * HTS SQL filter would not narrow anything. This test proves the Search API
 * variant renders the same cascade resolution and applies it as an "IN"
 * condition on the indexed term field, matching the whole selected subtree
 * without the Search API "Add hierarchy" processor.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
final class HtsSearchApiFilterTest extends KernelTestBase {

  use HtsSearchApiIndexTrait;

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
    'search_api',
    'search_api_db',
    'hts',
    'hts_search_api',
  ];

  /**
   * The Search API index id.
   */
  private const INDEX_ID = 'hts_test_index';

  /**
   * Term ids keyed by name.
   *
   * @var array<string, int>
   */
  private array $tids = [];

  /**
   * Node ids keyed by title.
   *
   * @var array<string, int>
   */
  private array $nids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig(['user', 'field', 'filter', 'node', 'taxonomy', 'search_api']);

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

    $this->nids['madrid'] = $this->createNode('Node Madrid', $this->tids['madrid']);
    $this->nids['france'] = $this->createNode('Node France', $this->tids['france']);

    $this->createHtsIndex(self::INDEX_ID);
    $this->createView();
  }

  /**
   * The class-swap makes the Search API term filter an HTS cascading filter.
   */
  public function testFilterUsesHtsClass(): void {
    $view = Views::getView('hts_sapi_test_view');
    $view->setDisplay('page_1');
    $view->initHandlers();
    $filter = $view->filter['field_hts_terms'];
    $this->assertInstanceOf(HtsSearchApiTerm::class, $filter);
    $view->destroy();
  }

  /**
   * Drilling to a leaf returns only the node tagged with that leaf.
   */
  public function testDrillToLeafReturnsLeafNode(): void {
    $titles = $this->executeWithSelection([
      '0' => (string) $this->tids['europe'],
      '1' => (string) $this->tids['spain'],
      '2' => (string) $this->tids['madrid'],
    ]);
    $this->assertSame(['Node Madrid'], $titles);
  }

  /**
   * Selecting a mid-level term returns nodes in its subtree.
   */
  public function testMidLevelReturnsSubtreeNodes(): void {
    $titles = $this->executeWithSelection([
      '0' => (string) $this->tids['europe'],
      '1' => (string) $this->tids['spain'],
    ]);
    $this->assertSame(['Node Madrid'], $titles);
  }

  /**
   * Selecting the root returns every node in the tree.
   */
  public function testRootReturnsAllNodes(): void {
    $titles = $this->executeWithSelection(['0' => (string) $this->tids['europe']]);
    $this->assertSame(['Node France', 'Node Madrid'], $titles);
  }

  /**
   * Drilling down a sibling branch returns only that branch's node.
   */
  public function testSiblingBranchReturnsSiblingNode(): void {
    $titles = $this->executeWithSelection([
      '0' => (string) $this->tids['europe'],
      '1' => (string) $this->tids['france'],
    ]);
    $this->assertSame(['Node France'], $titles);
  }

  /**
   * The resolved value applied to the query is the selected subtree.
   */
  public function testResolvedValueIsSubtree(): void {
    $view = Views::getView('hts_sapi_test_view');
    $view->setDisplay('page_1');
    $view->initHandlers();
    $filter = $view->filter['field_hts_terms'];
    $filter->acceptExposedInput([
      'field_hts_terms' => [
        'target_id' => '',
        'hierarchical_term_selector' => [
          'elements' => [
            '0' => (string) $this->tids['europe'],
            '1' => (string) $this->tids['spain'],
          ],
        ],
      ],
    ]);
    $value = array_map('intval', array_values((array) $filter->value));
    sort($value);
    $expected = [$this->tids['spain'], $this->tids['madrid']];
    sort($expected);
    $this->assertSame($expected, $value);
    $view->destroy();
  }

  /**
   * Executes the index-backed View with a cascade selection.
   *
   * @param array<string, string> $elements
   *   The per-level selected term ids.
   *
   * @return string[]
   *   The sorted node titles returned by the executed View.
   */
  private function executeWithSelection(array $elements): array {
    $view = Views::getView('hts_sapi_test_view');
    $view->setDisplay('page_1');
    $view->setExposedInput([
      'field_hts_terms' => [
        'target_id' => '',
        'hierarchical_term_selector' => ['elements' => $elements],
      ],
    ]);
    $view->execute();
    $titles = [];
    foreach ($view->result as $row) {
      $entity = $row->_object->getEntity();
      $titles[] = (string) $entity->label();
    }
    $view->destroy();
    sort($titles);
    return $titles;
  }

  /**
   * Creates a test node referencing a term.
   */
  private function createNode(string $title, int $tid): int {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'field_hts_terms' => [$tid],
      'status' => 1,
    ]);
    $node->save();
    return (int) $node->id();
  }

  /**
   * Creates the index-backed View exercised by the tests.
   */
  private function createView(): void {
    View::create([
      'id' => 'hts_sapi_test_view',
      'label' => 'HTS Search API Test View',
      'base_table' => 'search_api_index_' . self::INDEX_ID,
      'base_field' => 'search_api_id',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'query' => ['type' => 'search_api_query'],
            'cache' => ['type' => 'none'],
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'search_api_index_' . self::INDEX_ID,
                'field' => 'title',
                'plugin_id' => 'search_api_field',
              ],
            ],
            'filters' => [
              'field_hts_terms' => [
                'id' => 'field_hts_terms',
                'table' => 'search_api_index_' . self::INDEX_ID,
                'field' => 'field_hts_terms',
                'plugin_id' => 'hts_search_api_term',
                'exposed' => TRUE,
                'expose' => [
                  'operator_id' => 'field_hts_terms_op',
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
          'display_options' => ['path' => 'hts-sapi-test-view'],
        ],
      ],
    ])->save();
  }

}
