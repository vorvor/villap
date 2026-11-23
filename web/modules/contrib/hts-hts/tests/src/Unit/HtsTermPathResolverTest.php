<?php

namespace Drupal\Tests\hts\Unit;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\hts\Service\HtsTermPathResolver;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deterministic and A1 resolver rules without a Drupal kernel.
 */
#[Group('hts')]
class HtsTermPathResolverTest extends UnitTestCase {

  /**
   * Tests first-parent storage order.
   */
  public function testFirstParentPolicy(): void {
    $resolver = $this->resolver([
      1 => [],
      2 => [1],
      3 => [1],
      4 => [2, 3],
    ]);

    $this->assertSame([1, 2, 4], $resolver->ancestors(4));
  }

  /**
   * Tests stable all-parent expansion.
   */
  public function testAllParentPolicy(): void {
    $resolver = $this->resolver([
      1 => [],
      2 => [1],
      3 => [1],
      4 => [2, 3],
    ]);

    $this->assertSame([[1, 2, 4], [1, 3, 4]], $resolver->paths(4, 'all'));
    $this->assertSame([1, 2, 3, 4], $resolver->ancestors(4, 'all'));
  }

  /**
   * Tests A1 leaf-only semantics.
   */
  public function testLeafOnlySelectionRules(): void {
    $resolver = $this->resolver([
      1 => [],
      2 => [1],
      3 => [2],
    ]);

    $this->assertFalse($resolver->isLeafInSelection(2, [2, 3]));
    $this->assertTrue($resolver->isLeafInSelection(3, [2, 3]));
    $this->assertTrue($resolver->isLeafInSelection(2, [2]));
  }

  /**
   * Builds a resolver backed by mocked terms.
   *
   * @param array<int, array<int>> $parents
   *   Parent IDs keyed by term ID.
   */
  private function resolver(array $parents): HtsTermPathResolver {
    $terms = [];
    foreach ($parents as $tid => $parent_ids) {
      $parent_field = $this->createMock(FieldItemListInterface::class);
      $parent_field->method('getValue')->willReturn(array_map(
        static fn (int $parent_tid): array => ['target_id' => $parent_tid],
        $parent_ids,
      ));
      $term = $this->createMock(TermInterface::class);
      $term->method('id')->willReturn($tid);
      $term->method('get')->with('parent')->willReturn($parent_field);
      $terms[$tid] = $term;
    }

    $storage = $this->createMock(TermStorageInterface::class);
    $storage->method('load')->willReturnCallback(static fn (int $tid) => $terms[$tid] ?? NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('taxonomy_term')->willReturn($storage);

    return new HtsTermPathResolver(
      $this->createMock(SelectionPluginManagerInterface::class),
      $entity_type_manager,
      $this->createMock(EntityRepositoryInterface::class),
      $this->createMock(TranslationInterface::class),
    );
  }

}
