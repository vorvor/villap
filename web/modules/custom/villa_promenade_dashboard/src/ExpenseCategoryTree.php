<?php

namespace Drupal\villa_promenade_dashboard;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Builds category-first shortcuts to the native financial forms.
 */
final class ExpenseCategoryTree {

  use StringTranslationTrait;

  /**
   * Returns an access-aware, nested list of financial category links.
   */
  public function build(string $type = 'expense'): array {
    if (!in_array($type, ['expense', 'income'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported financial content type.');
    }
    $vocabulary = $type . '_category';
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['expense-category-picker']],
    ];
    $cache = new CacheableMetadata();
    $cache->addCacheTags(['taxonomy_term_list:' . $vocabulary, 'config:taxonomy.vocabulary.' . $vocabulary]);
    $cache->addCacheContexts(['languages:language_content']);
    $access = \Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess($type, NULL, [], TRUE);
    $cache->addCacheableDependency($access);
    $build['#access'] = $access->isAllowed();
    if (!$access->isAllowed()) {
      $cache->applyTo($build);
      return $build;
    }

    $children = [];
    $seen = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary, 0, NULL, TRUE) as $term) {
      // A multiple-parent term can occur more than once in loadTree().
      if (isset($seen[$term->id()])) {
        continue;
      }
      $seen[$term->id()] = TRUE;
      $term = \Drupal::service('entity.repository')->getTranslationFromContext($term);
      $cache->addCacheableDependency($term);
      $term_access = $term->access('view', NULL, TRUE);
      $cache->addCacheableDependency($term_access);
      if (!$term_access->isAllowed()) {
        continue;
      }
      foreach ($term->get('parent')->getValue() as $parent) {
        $children[(int) $parent['target_id']][$term->id()] = $term;
      }
    }

    $build['heading'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $type === 'income' ? $this->t('Start an income by category') : $this->t('Start an expense by category')];
    $build['tree'] = $this->buildBranch($children, 0, $type);
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Builds each branch in taxonomy weight/name order, guarding against cycles.
   */
  private function buildBranch(array $children, int $parent, string $type, array $path = []): array {
    $list = ['#type' => 'html_tag', '#tag' => 'ul', '#attributes' => ['class' => ['expense-category-tree']]];
    foreach ($children[$parent] ?? [] as $id => $term) {
      if (isset($path[$id])) {
        continue;
      }
      $list[$id] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        'link' => [
          '#type' => 'link',
          '#title' => $term->label(),
          '#url' => Url::fromRoute('node.add', ['node_type' => $type], [
            'query' => [
              $type . '_category' => $id,
              'villa_dashboard_dialog' => 1,
              'destination' => Url::fromRoute('villa_promenade_dashboard.page')->toString(),
            ],
          ]),
          '#attributes' => [
            'class' => ['expense-category-tree__link', 'expense-category-tree__link--level-' . min(count($path) + 1, 3), 'use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 'min(760px, 94vw)']),
          ],
        ],
      ];
      if (!empty($children[$id])) {
        $list[$id]['children'] = $this->buildBranch($children, (int) $id, $type, $path + [$id => TRUE]);
      }
    }
    return $list;
  }

}
