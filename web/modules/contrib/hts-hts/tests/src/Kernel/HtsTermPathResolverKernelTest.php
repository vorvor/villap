<?php

namespace Drupal\Tests\hts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel coverage for access, cacheability, translation, and parent policy.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
class HtsTermPathResolverKernelTest extends KernelTestBase {

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
    'taxonomy',
    'language',
    'hts',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter']);
    // Reserve uid 1 so the acting user is a normal account subject to access
    // checks, rather than the superuser who bypasses them.
    $this->createUser();
    $this->setCurrentUser($this->createUser(['access content']));
  }

  /**
   * Tests deterministic parents, translated labels, access, and cache tags.
   */
  public function testResolverKernelContract(): void {
    $vocabulary = $this->createVocabulary();
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

    $settings = [
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
      'target_bundles' => [$vocabulary->id() => $vocabulary->id()],
    ];
    $resolver = $this->container->get('hts.term_path_resolver')->forHandler($settings);
    $this->assertSame([(int) $root_a->id(), (int) $child->id()], $resolver->ancestors((int) $child->id()));
    $this->assertSame(
      [[(int) $root_a->id(), (int) $child->id()], [(int) $root_b->id(), (int) $child->id()]],
      $resolver->paths((int) $child->id(), 'all'),
    );

    $allowed = $resolver->label((int) $child->id());
    $this->assertSame('Child', (string) $allowed['label']);
    $this->assertContains('taxonomy_term:' . $child->id(), $allowed['cacheability']->getCacheTags());

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('language_manager')->reset();
    $child = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadUnchanged((int) $child->id());
    $child->addTranslation('fr', ['name' => 'Enfant']);
    $child->save();
    $translated = $this->container->get('hts.term_path_resolver')
      ->forHandler($settings + ['langcode' => 'fr'])
      ->label((int) $child->id());
    $this->assertSame('fr', $translated['entity']->language()->getId());
    $this->assertSame('Enfant', (string) $translated['label']);

    $hidden = Term::create([
      'vid' => $vocabulary->id(),
      'name' => 'Hidden',
      'status' => 0,
    ]);
    $hidden->save();
    $denied = $resolver->label((int) $hidden->id());
    $this->assertNull($denied['entity']);
    $this->assertNotContains('taxonomy_term:' . $hidden->id(), $denied['cacheability']->getCacheTags());
  }

}
