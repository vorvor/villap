<?php

namespace Drupal\villa_promenade\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

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

    // The hero supplies the front-page heading; retain titles on other pages.
    foreach ($variables['page'] as $region_name => $region) {
      if (!is_array($region)) {
        continue;
      }
      foreach ($region as $key => $block) {
        if (is_array($block) && ($block['#plugin_id'] ?? NULL) === 'page_title_block') {
          $variables['page'][$region_name][$key]['#access'] = FALSE;
        }
      }
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
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['front-intro__title']],
            '#weight' => -10,
            '#access' => $intro->get('title')->access('view', NULL, TRUE),
            'text' => ['#plain_text' => $intro->label()],
          ],
          'description' => $entity_type_manager->getViewBuilder('node')->viewField($intro->get('field_description'), [
            'label' => 'hidden',
            'type' => 'text_default',
          ]),
        ];
        $this->addNodeEditLink($variables['front_intro'], $intro);
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
    $cache->setCacheTags(['node_list', 'node_list:image_separator', 'config:core.extension']);
    $cache->setCacheContexts([
      'user.permissions',
      'user.node_grants:view',
      'languages:language_content',
    ]);
    $view_builder = $entity_type_manager->getViewBuilder('node');
    $nodes = $storage->loadMultiple($ids);
    $section_anchors = [
      'a ház' => 'a-haz',
      'szolgáltatásaink' => 'szolgaltatasaink',
      'galéria' => 'galeria',
      'ismerj meg minket' => 'rolunk',
      'tervezzünk együtt' => 'tervezzunk',
    ];
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
      $section_title = mb_strtolower(trim($node->label()), 'UTF-8');
      if (isset($section_anchors[$section_title])) {
        $build[$id]['#attributes']['id'] = $section_anchors[$section_title];
        // Avoid duplicate anchors if multiple separators share a title.
        unset($section_anchors[$section_title]);
      }
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
      $this->addNodeEditLink($build[$id], $node);
      if (mb_strtolower(trim($node->label()), 'UTF-8') === 'a ház' && !isset($build['image_text_8'])) {
        foreach ([8, 9, 10] as $image_text_id) {
          $cache->addCacheTags(['node:' . $image_text_id]);
          $build['image_text_' . $image_text_id] = [];
          if ($image_text = $storage->load($image_text_id)) {
            $image_text = \Drupal::service('entity.repository')->getTranslationFromContext($image_text);
            $image_text_access = $image_text->access('view', NULL, TRUE);
            $cache->addCacheableDependency($image_text);
            $cache->addCacheableDependency($image_text_access);
            if ($image_text_access->isAllowed()) {
              $build['image_text_' . $image_text_id] = $this->buildImageTextBlock($image_text);
            }
          }
        }
        $build['four_blocks'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['four-block-layout']],
          '#attached' => ['library' => ['villa_promenade/four-block-layout']],
        ];
        foreach ([11, 12, 13, 14] as $block_id) {
          $cache->addCacheTags(['node:' . $block_id]);
          if ($block = $storage->load($block_id)) {
            $block = \Drupal::service('entity.repository')->getTranslationFromContext($block);
            $block_access = $block->access('view', NULL, TRUE);
            $cache->addCacheableDependency($block);
            $cache->addCacheableDependency($block_access);
            if ($block_access->isAllowed()) {
              $row = $this->buildImageTextBlock($block);
              $row['#attributes']['class'][] = 'four-block-layout__row';
              if (in_array($block_id, [12, 14], TRUE)) {
                $row['#attributes']['class'][] = 'four-block-layout__row--reversed';
              }
              unset($row['inner']['gallery']);
              $build['four_blocks'][$block_id] = $row;
            }
          }
        }
      }
      if (mb_strtolower(trim($node->label()), 'UTF-8') === 'galéria' && !isset($build['photo_gallery'])) {
        $build['photo_gallery'] = $this->buildPhotoGallery($node);
        if ($build['photo_gallery']) {
          // The gallery replaces this separator's text panel in the design.
          unset($build[$id]['field_description']);
        }
      }
      if (mb_strtolower(trim($node->label()), 'UTF-8') === 'tervezzünk együtt' && \Drupal::moduleHandler()->moduleExists('villa_promenade_contact') && !isset($build['event_contact'])) {
        // An empty text panel would otherwise add padding above the form.
        if ($node->hasField('field_description') && $node->get('field_description')->isEmpty()) {
          unset($build[$id]['field_description']);
        }
        $contact_form = \Drupal::formBuilder()->getForm(\Drupal\villa_promenade_contact\Form\EventContactForm::class);
        $contact_form['name']['#title'] = t('Név:');
        $contact_form['email']['#title'] = t('E-mail:');
        $contact_form['event']['#title'] = t('Esemény:');
        $contact_form['event']['#empty_option'] = t('- Válassz eseményt -');
        $contact_form['event']['#options'][''] = $contact_form['event']['#empty_option'];
        $contact_form['actions']['submit']['#value'] = t('Küldés');
        $build['event_contact'] = [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'event-contact',
            'class' => ['front-event-contact'],
            'aria-labelledby' => 'event-contact-title',
          ],
          '#attached' => ['library' => ['villa_promenade/front-event-contact']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#attributes' => ['id' => 'event-contact-title', 'class' => ['front-event-contact__title']],
            '#value' => t('Minden esemény más. Kezdjük az alapoknál.'),
            '#weight' => -20,
          ],
          'intro' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['front-event-contact__intro']],
            '#value' => t('Írd meg milyen eseményt tervezel, mi pedig felvesszük veled a kapcsolatot a részletekkel.'),
            '#weight' => -10,
          ],
          'form' => $contact_form,
        ];
      }
      if (mb_strtolower(trim($node->label()), 'UTF-8') === 'ismerj meg minket' && !isset($build['team_members'])) {
        $build['team_members'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['team-members']],
          '#attached' => ['library' => ['villa_promenade/team-members']],
        ];
        foreach ([15, 16, 17, 18, 19] as $member_id) {
          $cache->addCacheTags(['node:' . $member_id]);
          if ($member = $storage->load($member_id)) {
            $member = \Drupal::service('entity.repository')->getTranslationFromContext($member);
            $member_access = $member->access('view', NULL, TRUE);
            $cache->addCacheableDependency($member);
            $cache->addCacheableDependency($member_access);
            if ($member_access->isAllowed() && $member->bundle() === 'team_member') {
              $build['team_members'][$member_id] = $this->buildTeamMemberBlock($member);
            }
          }
        }
      }
    }
    $cache->applyTo($build);
    $variables['image_separators'] = $build;
  }

  /**
   * Builds the front-page image/text block using the node's configured fields.
   */
  private function buildImageTextBlock(\Drupal\node\NodeInterface $node): array {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'article',
      '#attributes' => ['class' => ['image-text-block']],
      '#attached' => ['library' => ['villa_promenade/image-text-block']],
      'inner' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['image-text-block__inner']],
        'text' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['image-text-block__text']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['image-text-block__title']],
            '#access' => $node->get('title')->access('view', NULL, TRUE),
            'text' => ['#plain_text' => $node->label()],
          ],
        ],
      ],
    ];
    $images = [];
    foreach ($node->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      $items = $node->get($field_name);
      if ($definition->getType() === 'image') {
        foreach ($items as $item) {
          $images[] = $view_builder->viewFieldItem($item, ['label' => 'hidden', 'type' => 'image']);
        }
      }
      elseif (in_array($definition->getType(), ['text', 'text_long', 'text_with_summary', 'string_long'], TRUE)) {
        $build['inner']['text'][$field_name] = $view_builder->viewField($items, ['label' => 'hidden']);
      }
    }
    if ($images) {
      $build['inner']['image'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['image-text-block__image']],
        '#weight' => -10,
        'image' => array_shift($images),
      ];
      if ($images) {
        $build['inner']['gallery'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['image-text-block__gallery']],
          '#weight' => 10,
        ];
        foreach ($images as $delta => $image) {
          $build['inner']['gallery'][$delta] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['image-text-block__gallery-item']],
            'image' => $image,
          ];
        }
      }
    }
    else {
      $build['#attributes']['class'][] = 'image-text-block--text-only';
    }
    CacheableMetadata::createFromObject($node)->applyTo($build);
    $this->addNodeEditLink($build, $node);
    return $build;
  }

  /**
   * Builds a member portrait, name, titulus, and description.
   */
  private function buildTeamMemberBlock(\Drupal\node\NodeInterface $node): array {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'article',
      '#attributes' => ['class' => ['team-member']],
      'portrait' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['team-member__portrait']],
      ],
      'text' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['team-member__text']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['team-member__title']],
          '#access' => $node->get('title')->access('view', NULL, TRUE),
          'text' => ['#plain_text' => $node->label()],
        ],
      ],
    ];
    if ($node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
      $build['portrait']['image'] = $view_builder->viewFieldItem($node->get('field_image')->first(), [
        'label' => 'hidden',
        'type' => 'image',
      ]);
    }
    foreach (['field_titulus' => 'team-member__titulus', 'field_description' => 'team-member__description'] as $field_name => $class) {
      if ($node->hasField($field_name)) {
        $build['text'][$field_name] = [
          '#type' => 'container',
          '#attributes' => ['class' => [$class]],
          'field' => $view_builder->viewField($node->get($field_name), ['label' => 'hidden']),
        ];
      }
    }
    CacheableMetadata::createFromObject($node)->applyTo($build);
    $this->addNodeEditLink($build, $node);
    return $build;
  }

  /**
   * Builds an editable mosaic from the gallery separator's image field.
   */
  private function buildPhotoGallery(\Drupal\node\NodeInterface $node): array {
    if (!$node->hasField('field_gallery_images') || $node->get('field_gallery_images')->isEmpty()) {
      return [];
    }
    $items = $node->get('field_gallery_images');
    $access = $items->access('view', NULL, TRUE);
    $cache = CacheableMetadata::createFromObject($node)->addCacheableDependency($access);
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $file_url_generator = \Drupal::service('file_url_generator');
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#attributes' => ['class' => ['photo-gallery'], 'aria-label' => $node->label()],
      '#attached' => ['library' => ['villa_promenade/photo-gallery']],
      '#access' => $access,
    ];
    $position = 0;
    foreach ($items as $item) {
      if (!$item->entity) {
        continue;
      }
      $group = intdiv($position, 10);
      $slot = $position % 10 + 1;
      if (!isset($build[$group])) {
        $build[$group] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['photo-gallery__grid']],
        ];
      }
      $cache->addCacheableDependency($item->entity);
      $build[$group][$slot] = [
        '#type' => 'link',
        '#title' => $view_builder->viewFieldItem($item, [
          'label' => 'hidden',
          'type' => 'image',
          'settings' => ['image_style' => 'gallery_preview'],
        ]),
        '#url' => $file_url_generator->generate($item->entity->getFileUri()),
        '#attributes' => [
          'class' => ['photo-gallery__item', 'photo-gallery__item--' . $slot],
          'aria-label' => \Drupal::translation()->translate('View photo: @description', ['@description' => $item->alt]),
        ],
      ];
      $position++;
    }
    $cache->applyTo($build);
    $this->addNodeEditLink($build, $node);
    return $build;
  }

  /**
   * Adds an edit shortcut when the current account may update the node.
   */
  private function addNodeEditLink(array &$build, \Drupal\node\NodeInterface $node): void {
    $access = $node->access('update', NULL, TRUE);
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->applyTo($build);
    if (!$access->isAllowed()) {
      return;
    }
    $build['#attributes']['class'][] = 'front-editable';
    $build['#attached']['library'][] = 'villa_promenade/front-edit-links';
    $build['edit_link'] = [
      '#type' => 'link',
      '#title' => \Drupal::translation()->translate('Edit @title', ['@title' => $node->label()]),
      '#url' => $node->toUrl('edit-form', [
        'query' => ['destination' => Url::fromRoute('<front>')->toString()],
      ]),
      '#attributes' => ['class' => ['front-edit-link']],
      '#weight' => -100,
      '#access' => $access,
    ];
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
