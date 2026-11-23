<?php

namespace Drupal\Tests\hts_bef\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional JavaScript coverage for optional BEF 7 and Select2 rows.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
class HtsBefTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
    'views',
    'hts',
    'better_exposed_filters',
    'hts_bef',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'page', 'name' => 'Page']);
    Vocabulary::create(['vid' => 'topics', 'name' => 'Topics'])->save();
    $term = Term::create(['vid' => 'topics', 'name' => 'Topic']);
    $term->save();
    FieldStorageConfig::create([
      'field_name' => 'field_topics',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_topics',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Topics',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['topics' => 'topics']],
      ],
    ])->save();
    $this->createNode([
      'type' => 'page',
      'title' => 'Topic content',
      'field_topics' => [['target_id' => $term->id()]],
    ]);
    $this->createView();
  }

  /**
   * Tests core Views and plain-select fallbacks without optional modules.
   */
  public function testOptionalModulesAbsent(): void {
    $this->drupalGet('hts-bef-test');
    $this->assertSession()->elementExists('css', '.hts-select');
    $this->assertSession()->elementNotExists('css', '.hts-select2');
    $this->assertSession()->elementNotExists('css', '.hts-bef');
  }

  /**
   * Tests BEF 7 and Select2 when both extension rows are provisioned.
   */
  public function testOptionalModulesPresent(): void {
    $extension_list = $this->container->get('extension.list.module');
    if (!$extension_list->exists('better_exposed_filters') || !$extension_list->exists('select2')) {
      $this->markTestSkipped('The BEF 7 + Select2-present harness row is not provisioned.');
    }
    // Select2 renders through its front-end asset library, which is not a
    // Composer dependency. Skip when the library is absent so the row does not
    // fail on an environment that lacks it.
    if (!file_exists(DRUPAL_ROOT . '/libraries/select2/dist/js/select2.min.js')) {
      $this->markTestSkipped('The Select2 asset library is not installed.');
    }
    $this->container->get('module_installer')->install(['select2']);

    $view = View::load('hts_bef_test');
    $display = $view->get('display');
    $display['default']['display_options']['exposed_form'] = [
      'type' => 'bef',
      'options' => [
        'submit_button' => 'Apply',
        'reset_button' => FALSE,
        'exposed_sorts_label' => 'Sort by',
        'expose_sort_order' => TRUE,
        'bef' => [
          'general' => [
            'autosubmit' => FALSE,
            'allow_secondary' => FALSE,
          ],
          'sort' => ['plugin_id' => 'default'],
          'pager' => ['plugin_id' => 'default'],
          'filter' => [
            'topics' => ['plugin_id' => 'hts'],
          ],
        ],
      ],
    ];
    $display['default']['display_options']['filters']['topics']['hierarchical_term_selector']['widget_type'] = 'select2';
    $view->set('display', $display);
    $view->save();

    $this->drupalGet('hts-bef-test');
    $this->assertSession()->elementExists('css', '.hts-bef');
    $this->assertSession()->elementExists('css', '.hts-select2');
  }

  /**
   * Creates a page View with one exposed HTS taxonomy filter.
   */
  private function createView(): void {
    View::create([
      'id' => 'hts_bef_test',
      'label' => 'HTS BEF test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'status' => TRUE,
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 1,
          'display_options' => [
            'access' => ['type' => 'none', 'options' => []],
            'cache' => ['type' => 'none', 'options' => []],
            'query' => ['type' => 'views_query', 'options' => []],
            'exposed_form' => [
              'type' => 'basic',
              'options' => [
                'submit_button' => 'Apply',
                'reset_button' => FALSE,
                'exposed_sorts_label' => 'Sort by',
                'expose_sort_order' => TRUE,
              ],
            ],
            'pager' => ['type' => 'none', 'options' => []],
            'style' => ['type' => 'default', 'options' => []],
            'row' => ['type' => 'fields', 'options' => []],
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
            'filters' => $this->viewFilters(),
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_title' => 'Page',
          'position' => 2,
          'display_options' => [
            'display_extenders' => [],
            'path' => 'hts-bef-test',
          ],
        ],
      ],
    ])->save();
    // Register the page display's route so the test can visit it.
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Builds the exposed filter definitions for the test View.
   *
   * @return array<string, array<string, mixed>>
   *   The status and HTS topics filter definitions.
   */
  private function viewFilters(): array {
    return [
      'status' => [
        'id' => 'status',
        'table' => 'node_field_data',
        'field' => 'status',
        'value' => 1,
        'plugin_id' => 'boolean',
        'entity_type' => 'node',
        'entity_field' => 'status',
      ],
      'topics' => [
        'id' => 'topics',
        'table' => 'taxonomy_index',
        'field' => 'tid',
        'relationship' => 'none',
        'group_type' => 'group',
        'operator' => 'or',
        'value' => [],
        'group' => 1,
        'exposed' => TRUE,
        'expose' => [
          'operator_id' => 'topics_op',
          'label' => 'Topics',
          'description' => '',
          'use_operator' => FALSE,
          'operator' => '',
          'identifier' => 'topics',
          'required' => FALSE,
          'remember' => FALSE,
          'multiple' => FALSE,
          'reduce' => FALSE,
        ],
        'is_grouped' => FALSE,
        'reduce_duplicates' => FALSE,
        'type' => 'hts',
        'limit' => TRUE,
        'vid' => 'topics',
        'hierarchy' => TRUE,
        'error_message' => TRUE,
        'hierarchical_term_selector' => [
          'hierarchy_depth' => 0,
          'label_level' => 'Parent,Child',
          'description_level' => '',
          'none_label' => '- Any -',
          'widget_type' => 'select',
          'label_hide' => TRUE,
          'multiple_parent_policy' => 'first',
        ],
        'plugin_id' => 'taxonomy_index_tid',
      ],
    ];
  }

}
