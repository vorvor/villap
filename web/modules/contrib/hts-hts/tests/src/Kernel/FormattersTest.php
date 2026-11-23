<?php

namespace Drupal\Tests\hts\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel coverage for formatter access, parent policy, and A1 behavior.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
class FormattersTest extends KernelTestBase {

  use TaxonomyTestTrait;
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
    'node',
    'taxonomy',
    'hts',
  ];

  /**
   * The tested field definition.
   */
  private FieldConfig $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter']);
    // Reserve uid 1 so the acting user is a normal account subject to access
    // checks, rather than the superuser who bypasses them.
    $this->createUser();
    $this->setCurrentUser($this->createUser(['access content']));

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_terms',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
  }

  /**
   * Tests two parents, first/all output, A1, and inaccessible terms.
   */
  public function testFormatterContracts(): void {
    $vocabulary = $this->createVocabulary();
    $this->field = FieldConfig::create([
      'field_name' => 'field_terms',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Terms',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [$vocabulary->id() => $vocabulary->id()],
        ],
      ],
    ]);
    $this->field->save();

    $root_a = Term::create(['vid' => $vocabulary->id(), 'name' => 'Root A']);
    $root_a->save();
    $root_b = Term::create(['vid' => $vocabulary->id(), 'name' => 'Root B']);
    $root_b->save();
    $child = Term::create([
      'vid' => $vocabulary->id(),
      'name' => 'Child',
      'parent' => [
        ['target_id' => $root_a->id()],
        ['target_id' => $root_b->id()],
      ],
    ]);
    $child->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Formatter fixture',
      'field_terms' => [
        ['target_id' => $root_a->id()],
        ['target_id' => $child->id()],
      ],
    ]);
    $node->save();

    $breadcrumb = $this->renderFormatter('hts_breadcrumb_path', $node, ['delimiter' => ' / ']);
    $this->assertStringContainsString('Root A', $breadcrumb);
    $this->assertStringContainsString('Child', $breadcrumb);
    $this->assertStringNotContainsString('Root B', $breadcrumb);

    $all = $this->renderFormatter('hts_hierarchical_list', $node, ['multiple_parent_policy' => 'all']);
    $this->assertStringContainsString('Root A', $all);
    $this->assertStringContainsString('Root B', $all);
    $this->assertStringContainsString('Child', $all);

    $first = $this->renderFormatter('hts_hierarchical_list', $node, ['multiple_parent_policy' => 'first']);
    $this->assertStringContainsString('Root A', $first);
    $this->assertStringNotContainsString('Root B', $first);

    $leaf = $this->renderFormatter('hts_leaf_only', $node);
    $this->assertStringContainsString('Child', $leaf);
    $this->assertStringNotContainsString('Root A', $leaf);

    $lone_parent = Node::create([
      'type' => 'page',
      'title' => 'Lone parent fixture',
      'field_terms' => [['target_id' => $root_a->id()]],
    ]);
    $lone_parent->save();
    $this->assertStringContainsString('Root A', $this->renderFormatter('hts_leaf_only', $lone_parent));

    $hidden = Term::create([
      'vid' => $vocabulary->id(),
      'name' => 'Hidden',
      'status' => 0,
    ]);
    $hidden->save();
    $hidden_node = Node::create([
      'type' => 'page',
      'title' => 'Hidden fixture',
      'field_terms' => [['target_id' => $hidden->id()]],
    ]);
    $hidden_node->save();
    $build = $this->formatter('hts_leaf_only')->viewElements($hidden_node->get('field_terms'), 'en');
    $this->assertNotContains('taxonomy_term:' . $hidden->id(), $build['#cache']['tags'] ?? []);
    $this->assertSame('', trim((string) $this->container->get('renderer')->renderInIsolation($build)));
  }

  /**
   * Renders one formatter against a node field.
   */
  private function renderFormatter(string $plugin_id, Node $node, array $settings = []): string {
    $build = $this->formatter($plugin_id, $settings)->viewElements($node->get('field_terms'), 'en');
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Creates a formatter plugin instance.
   */
  private function formatter(string $plugin_id, array $settings = []): object {
    return $this->container->get('plugin.manager.field.formatter')->createInstance($plugin_id, [
      'field_definition' => $this->field,
      'settings' => $settings,
      'label' => 'hidden',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);
  }

}
