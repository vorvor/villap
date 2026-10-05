<?php

namespace Drupal\villa_promenade\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for villa_promenade.
 */
class VillaPromenadeHooks {
  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    if (empty($variables['is_front'])) {
      return;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('node');
    $variables['front_intro'] = [];
    $intro_cache = new CacheableMetadata();
    $intro_cache->setCacheTags(['node_list', 'node:7']);
    $intro_cache->setCacheContexts(['languages:language_content']);
    if ($intro = $storage->load(7)) {
      $intro = \Drupal::service('entity.repository')->getTranslationFromContext($intro);
      $intro_access = $intro->access('view', NULL, TRUE);
      $intro_cache->addCacheableDependency($intro);
      $intro_cache->addCacheableDependency($intro_access);
      if ($intro_access->isAllowed() && $intro->hasField('field_description')) {
        $variables['front_intro'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['front-intro', 'image-separator__description']],
          '#attached' => ['library' => ['villa_promenade/image-separator']],
          'description' => $entity_type_manager->getViewBuilder('node')->viewField($intro->get('field_description'), [
            'label' => 'hidden',
            'type' => 'text_default',
          ]),
        ];
      }
    }
    CacheableMetadata::createFromRenderArray($variables['front_intro'])
      ->merge($intro_cache)
      ->applyTo($variables['front_intro']);

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'image_separator')
      ->condition('status', 1)
      ->sort('created', 'ASC')
      ->sort('nid', 'ASC')
      ->execute();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['image-separators']],
      '#attached' => ['library' => ['villa_promenade/image-separator']],
    ];
    $cache = new CacheableMetadata();
    $cache->setCacheTags(['node_list', 'node_list:image_separator']);
    $cache->setCacheContexts([
      'user.permissions',
      'user.node_grants:view',
      'languages:language_content',
    ]);
    $view_builder = $entity_type_manager->getViewBuilder('node');
    $nodes = $storage->loadMultiple($ids);
    foreach ($ids as $id) {
      $node = \Drupal::service('entity.repository')->getTranslationFromContext($nodes[$id]);
      $access = $node->access('view', NULL, TRUE);
      $cache->addCacheableDependency($node);
      $cache->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $classes = ['image-separator'];
      if (mb_strtolower(trim($node->label()), 'UTF-8') === 'a múlt') {
        $classes[] = 'image-separator--grayscale';
      }

      $build[$id] = [
        '#type' => 'html_tag',
        '#tag' => 'article',
        '#attributes' => ['class' => $classes],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['image-separator__title']],
          '#weight' => 10,
          '#access' => $node->get('title')->access('view', NULL, TRUE),
          'text' => ['#plain_text' => $node->label()],
        ],
      ];
      foreach (['field_description' => 'text_default', 'field_image' => 'image'] as $field_name => $formatter) {
        if ($node->hasField($field_name)) {
          $build[$id][$field_name] = $view_builder->viewField($node->get($field_name), [
            'label' => 'hidden',
            'type' => $formatter,
          ]);
          // Keep field access and formatter cache metadata on the render array.
          $class = $field_name === 'field_description' ? 'image-separator__description' : 'image-separator__image';
          $build[$id][$field_name]['#prefix'] = '<div class="' . $class . '">';
          $build[$id][$field_name]['#suffix'] = '</div>';
          $build[$id][$field_name]['#weight'] = $field_name === 'field_description' ? 20 : 0;
        }
      }
    }
    $cache->applyTo($build);
    $variables['image_separators'] = $build;
  }

  /**
   * @file
   * Functions to support theming.
   */

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

}
