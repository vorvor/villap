<?php

namespace Drupal\Tests\hts\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\JSWebAssert;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional JavaScript coverage for HTS AJAX level rebuilding.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
class HtsWidgetAjaxTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

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
    'hts',
  ];

  /**
   * Root term used by the browser test.
   */
  private Term $rootTerm;

  /**
   * Child term used by the browser test.
   */
  private Term $childTerm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'page', 'name' => 'Page']);
    $vocabulary = Vocabulary::create(['vid' => 'topics', 'name' => 'Topics']);
    $vocabulary->save();
    $this->rootTerm = Term::create(['vid' => 'topics', 'name' => 'Root']);
    $this->rootTerm->save();
    $this->childTerm = Term::create([
      'vid' => 'topics',
      'name' => 'Child',
      'parent' => [['target_id' => $this->rootTerm->id()]],
    ]);
    $this->childTerm->save();

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
        'handler_settings' => [
          'target_bundles' => ['topics' => 'topics'],
        ],
      ],
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'page', 'default')
      ->setComponent('field_topics', [
        'type' => 'hierarchical_term_selector',
        'settings' => [
          'hierarchical_term_selector' => [
            'hierarchy_depth' => 0,
            'label_level' => 'Parent,Child',
            'description_level' => '',
            'none_label' => '- Any -',
            'widget_type' => 'select',
            'label_hide' => FALSE,
            'multiple_parent_policy' => 'first',
          ],
        ],
      ])->save();

    $this->drupalLogin($this->createUser(['access content', 'create page content']));
  }

  /**
   * Tests unlimited plain levels and preservation of unrelated messages.
   */
  public function testPlainAjaxRebuildPreservesMessages(): void {
    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();
    $this->assertSession()->elementExists('css', '.hts-select select');
    $this->assertSession()->elementNotExists('css', '.hts-select2');

    $this->getSession()->evaluateScript(<<<'JS'
      document.body.insertAdjacentHTML('afterbegin', '<div id="hts-message-fixture" class="messages messages--status">Keep this message.</div>');
JS);
    $root = $page->find('css', '.hts-select select');
    $root->selectOption((string) $this->rootTerm->id());
    $assert_session = $this->assertSession();
    assert($assert_session instanceof JSWebAssert);
    $assert_session->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Keep this message.');
    $selects = $page->findAll('css', '.hts-select select');
    $this->assertCount(2, $selects);
    $this->assertNotNull($selects[1]->find('css', 'option[value="' . $this->childTerm->id() . '"]'));
  }

}
