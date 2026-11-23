<?php

namespace Drupal\hts\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;

/**
 * Provides taxonomy tree operations for the Hierarchical Term Selector.
 */
class TermTreeHelper {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TermTreeHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns the storage of the "taxonomy_vocabulary" entities.
   *
   * @return \Drupal\taxonomy\VocabularyStorageInterface
   *   The vocabulary storage.
   */
  public function getVocabularyStorage(): VocabularyStorageInterface {
    /** @var \Drupal\taxonomy\VocabularyStorageInterface $storage */
    $storage = $this->getStorage('taxonomy_vocabulary');
    return $storage;
  }

  /**
   * Returns the storage of the "taxonomy_term" entities.
   *
   * @return \Drupal\taxonomy\TermStorageInterface
   *   The term storage.
   */
  public function getTermStorage(): TermStorageInterface {
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->getStorage('taxonomy_term');
    return $storage;
  }

  /**
   * Loads a single taxonomy term.
   *
   * @param int $tid
   *   The taxonomy term ID.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The loaded term, or NULL if it does not exist.
   */
  public function getTerm(int $tid): ?TermInterface {
    /** @var \Drupal\taxonomy\TermInterface|null $term */
    $term = $this->getTermStorage()->load($tid);
    return $term;
  }

  /**
   * Gets the number of levels required to represent selected vocabularies.
   *
   * Cycles and repeated term IDs are the only traversal stop condition.
   *
   * @param array<int, string> $bundles
   *   Vocabulary IDs.
   *
   * @return int
   *   The maximum hierarchy depth, including the root level.
   */
  public function getMaximumDepth(array $bundles): int {
    $maximum = 1;
    foreach ($this->getVocabularyStorage()->getToplevelTids($bundles) as $tid) {
      $maximum = max($maximum, $this->measureBranch((int) $tid, []));
    }
    return $maximum;
  }

  /**
   * Gets terms by hierarchy level.
   *
   * @param int $level
   *   The desired hierarchy level.
   * @param array<int, string> $bundles
   *   The bundles to search.
   *
   * @return array<int|string, array<string, mixed>>
   *   An array of term option data keyed by term ID.
   */
  public function getTermsByLevel(int $level, array $bundles): array {
    $options = [];
    foreach ($bundles as $bundle) {
      $terms = $this->getTermStorage()->loadTree($bundle, 0, $level + 1, TRUE);
      foreach ($terms as $term) {
        if (!$term instanceof TermInterface) {
          continue;
        }
        if ($this->getTermDepth($term) === $level) {
          $id = $term->id();
          $options[$id]['name'] = $term->label();
          $options[$id]['weight'] = $term->get('weight')->value;
        }
      }
    }
    return $options;
  }

  /**
   * Gets the depth of a term in the hierarchy.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term entity.
   *
   * @return int
   *   The depth of the term.
   */
  public function getTermDepth(TermInterface $term): int {
    $depth = 0;
    while ($term->get('parent')->target_id) {
      $parent = $this->getTerm((int) $term->get('parent')->target_id);
      if (!$parent) {
        break;
      }
      $term = $parent;
      $depth++;
    }

    return $depth;
  }

  /**
   * Recursively gets all term IDs under a term in a specific vocabulary.
   *
   * @param int $tid
   *   The taxonomy term ID from which to start.
   * @param string $vocabulary_id
   *   The vocabulary machine name.
   *
   * @return array<int, int|string>
   *   An array of term IDs including the given term ID.
   */
  public function getTaxonomyTreeTerms($tid, string $vocabulary_id): array {
    $term_storage = $this->getTermStorage();
    $term_ids = [];
    $term = $term_storage->load($tid);

    if ($term && $term->bundle() === $vocabulary_id) {
      $term_ids = [$tid];
      $child_term_ids = $this->getChildTerms($tid, $vocabulary_id);
      if (!empty($child_term_ids)) {
        $term_ids = array_merge($term_ids, $child_term_ids);
      }
    }

    return $term_ids;
  }

  /**
   * Recursively gets child term IDs of a given term.
   *
   * @param int $tid
   *   The taxonomy term ID from which to start.
   * @param string $vocabulary_id
   *   The vocabulary machine name.
   *
   * @return array<int, int|string>
   *   An array of child term IDs.
   */
  public function getChildTerms($tid, string $vocabulary_id): array {
    $child_terms = $this->getTermStorage()->loadChildren((int) $tid);

    $term_ids = [];
    foreach ($child_terms as $child_term) {
      if ($child_term->bundle() === $vocabulary_id) {
        $term_ids[] = (int) $child_term->id();
        $grandchildren_ids = $this->getChildTerms((int) $child_term->id(), $vocabulary_id);
        if (!empty($grandchildren_ids)) {
          $term_ids = array_merge($term_ids, $grandchildren_ids);
        }
      }
    }

    return $term_ids;
  }

  /**
   * Measures one hierarchy branch without revisiting term IDs.
   *
   * @param int $tid
   *   Current term ID.
   * @param array<int, bool> $visited
   *   IDs already visited in this branch.
   *
   * @return int
   *   Branch depth including the current term.
   */
  private function measureBranch(int $tid, array $visited): int {
    if (isset($visited[$tid])) {
      return 0;
    }
    $visited[$tid] = TRUE;
    $depth = 1;
    foreach ($this->getTermStorage()->loadChildren($tid) as $child) {
      $depth = max($depth, 1 + $this->measureBranch((int) $child->id(), $visited));
    }
    return $depth;
  }

  /**
   * Returns the entity storage for a given entity type.
   *
   * @param string $entity_type
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The entity storage.
   */
  private function getStorage(string $entity_type): EntityStorageInterface {
    return $this->entityTypeManager->getStorage($entity_type);
  }

}
