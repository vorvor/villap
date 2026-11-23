<?php

namespace Drupal\hts\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;

/**
 * Resolves taxonomy paths through an entity-reference selection handler.
 */
class HtsTermPathResolver {

  /**
   * Selection handler settings for this resolver instance.
   *
   * @var array<string, mixed>
   */
  protected array $handlerSettings = [];

  /**
   * Constructs an HTS term path resolver.
   */
  public function __construct(
    protected SelectionPluginManagerInterface $selectionManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected TranslationInterface $stringTranslation,
  ) {}

  /**
   * Returns an isolated resolver configured for one field or filter.
   *
   * @param array<string, mixed> $handler_settings
   *   Entity reference handler settings plus an optional "handler" key.
   *
   * @return static
   *   A configured clone.
   */
  public function forHandler(array $handler_settings): static {
    $resolver = clone $this;
    $resolver->handlerSettings = $handler_settings;
    return $resolver;
  }

  /**
   * Returns ancestor IDs in deterministic root-to-term order.
   *
   * The "first" policy follows the first parent in field storage order. The
   * "all" policy returns the stable union of every stored parent path.
   *
   * @param int $tid
   *   The taxonomy term ID.
   * @param string $policy
   *   Either "first" or "all".
   *
   * @return array<int>
   *   Ancestor IDs including the requested term.
   */
  public function ancestors(int $tid, string $policy = 'first'): array {
    $paths = $this->paths($tid, $policy);
    if ($paths === []) {
      return [];
    }
    if ($policy === 'first') {
      return $paths[0];
    }

    $ancestors = [];
    foreach ($paths as $path) {
      foreach (array_slice($path, 0, -1) as $ancestor) {
        $ancestors[$ancestor] = $ancestor;
      }
    }
    $ancestors[$tid] = $tid;
    return array_values($ancestors);
  }

  /**
   * Returns every deterministic root-to-term path.
   *
   * @param int $tid
   *   The taxonomy term ID.
   * @param string $policy
   *   Either "first" or "all".
   *
   * @return array<array<int>>
   *   One or more root-to-term paths.
   */
  public function paths(int $tid, string $policy = 'first'): array {
    if (!in_array($policy, ['first', 'all'], TRUE)) {
      throw new \InvalidArgumentException('The multiple-parent policy must be "first" or "all".');
    }
    $paths = $this->buildPaths($tid, [], $policy);
    $unique = [];
    foreach ($paths as $path) {
      $unique[implode(':', $path)] = $path;
    }
    return array_values($unique);
  }

  /**
   * Resolves an access-safe translated term label and its cacheability.
   *
   * Entity cache tags are added only after both entity access and configured
   * entity-reference selection allow the term.
   *
   * @param int $tid
   *   The taxonomy term ID.
   *
   * @return array{entity: ?\Drupal\taxonomy\TermInterface, label: ?\Drupal\Core\StringTranslation\TranslatableMarkup, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   The translated term, translated label and cacheability metadata.
   */
  public function label(int $tid): array {
    $cacheability = new CacheableMetadata();
    $term = $this->termStorage()->load($tid);
    if (!$term instanceof TermInterface) {
      return [
        'entity' => NULL,
        'label' => NULL,
        'cacheability' => $cacheability,
      ];
    }

    $access = $term->access('view label', NULL, TRUE);
    $cacheability->addCacheableDependency($access);
    $referenceable = array_map('intval', $this->selectionHandler()->validateReferenceableEntities([$tid]));
    if (!$access->isAllowed() || !in_array($tid, $referenceable, TRUE)) {
      $cacheability->setCacheTags(array_values(array_diff($cacheability->getCacheTags(), $term->getCacheTags())));
      return [
        'entity' => NULL,
        'label' => NULL,
        'cacheability' => $cacheability,
      ];
    }

    $langcode = isset($this->handlerSettings['langcode']) && is_string($this->handlerSettings['langcode'])
      ? $this->handlerSettings['langcode']
      : NULL;
    $translated = $langcode !== NULL
      ? $this->entityRepository->getTranslationFromContext($term, $langcode)
      : $this->entityRepository->getTranslationFromContext($term);
    $cacheability->addCacheableDependency($translated);

    $label_string = (string) ($translated->label() ?? '');
    $label = $this->stringTranslation->translate('@label', ['@label' => $label_string]);

    return [
      'entity' => $translated,
      'label' => $label,
      'cacheability' => $cacheability,
    ];
  }

  /**
   * Resolves selected terms, optionally including all descendants.
   *
   * @param array<int> $selected
   *   Selected term IDs.
   * @param bool $include_descendants
   *   Whether to include descendants.
   *
   * @return array{ids: array<int>, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   Accessible IDs and their merged access/entity cacheability.
   */
  public function resolveSelection(array $selected, bool $include_descendants = FALSE): array {
    $queue = array_values(array_unique(array_map('intval', $selected)));
    $resolved = [];
    $visited = [];
    $cacheability = new CacheableMetadata();

    while ($queue !== []) {
      $tid = array_shift($queue);
      if ($tid <= 0 || isset($visited[$tid])) {
        continue;
      }
      $visited[$tid] = TRUE;
      $result = $this->label($tid);
      $cacheability = $cacheability->merge($result['cacheability']);
      if ($result['entity'] instanceof TermInterface) {
        $resolved[$tid] = $tid;
      }
      if ($include_descendants) {
        $children = $this->termStorage()->loadChildren($tid);
        foreach ($children as $child) {
          $queue[] = (int) $child->id();
        }
      }
    }

    return [
      'ids' => array_values($resolved),
      'cacheability' => $cacheability,
    ];
  }

  /**
   * Checks A1 leaf-only semantics within a selected term set.
   *
   * @param int $tid
   *   The candidate taxonomy term ID.
   * @param array<int> $selected
   *   Selected taxonomy term IDs.
   *
   * @return bool
   *   TRUE when no other selected term descends from the candidate.
   */
  public function isLeafInSelection(int $tid, array $selected): bool {
    foreach (array_unique(array_map('intval', $selected)) as $selected_tid) {
      if ($selected_tid === $tid) {
        continue;
      }
      $path = $this->ancestors($selected_tid, 'all');
      if (in_array($tid, array_slice($path, 0, -1), TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Builds stored parent paths while breaking only cycles or repeated IDs.
   *
   * @param int $tid
   *   The term ID.
   * @param array<int, bool> $visited
   *   IDs already present in the current path.
   * @param string $policy
   *   The multiple-parent policy.
   *
   * @return array<array<int>>
   *   Root-to-term paths.
   */
  private function buildPaths(int $tid, array $visited, string $policy): array {
    if (isset($visited[$tid])) {
      return [];
    }
    $term = $this->termStorage()->load($tid);
    if (!$term instanceof TermInterface) {
      return [];
    }

    $visited[$tid] = TRUE;
    $parents = $this->parentIds($term);
    if ($policy === 'first' && $parents !== []) {
      $parents = [reset($parents)];
    }
    if ($parents === []) {
      return [[$tid]];
    }

    $paths = [];
    foreach ($parents as $parent_tid) {
      foreach ($this->buildPaths($parent_tid, $visited, $policy) as $path) {
        $path[] = $tid;
        $paths[] = $path;
      }
    }
    return $paths ?: [[$tid]];
  }

  /**
   * Returns non-root parent IDs in field storage order.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term entity.
   *
   * @return array<int>
   *   Parent IDs.
   */
  private function parentIds(TermInterface $term): array {
    $parents = [];
    foreach ($term->get('parent')->getValue() as $item) {
      $parent_tid = (int) ($item['target_id'] ?? 0);
      if ($parent_tid > 0) {
        $parents[] = $parent_tid;
      }
    }
    return array_values(array_unique($parents));
  }

  /**
   * Returns the configured entity-reference selection handler.
   *
   * The selection plugin manager returns an object cast to SelectionInterface
   * because the manager itself is typed as SelectionPluginManagerInterface.
   */
  private function selectionHandler(): SelectionInterface {
    $options = $this->handlerSettings;
    unset($options['langcode'], $options['multiple_parent_policy']);
    $options += [
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
    ];
    $plugin = $this->selectionManager->getInstance($options);
    if (!$plugin instanceof SelectionInterface) {
      throw new \RuntimeException('Entity reference selection handler could not be loaded.');
    }
    return $plugin;
  }

  /**
   * Returns taxonomy term storage.
   */
  private function termStorage(): TermStorageInterface {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

}
