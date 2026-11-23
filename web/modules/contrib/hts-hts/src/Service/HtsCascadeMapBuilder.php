<?php

namespace Drupal\hts\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hts\HtsConstants;
use Drupal\taxonomy\TermInterface;

/**
 * Builds access-safe options and client-side cascade maps for HTS elements.
 *
 * Owns the access filtering of raw term options and the parent-keyed cascade
 * maps consumed by the browser, keeping the element builder focused on
 * assembling the server-side render array.
 */
class HtsCascadeMapBuilder {

  /**
   * Constructs a HtsCascadeMapBuilder object.
   *
   * @param \Drupal\hts\Service\HtsOptionsBuilder $optionsBuilder
   *   The options builder.
   * @param \Drupal\hts\Service\TermTreeHelper $termTreeHelper
   *   The taxonomy tree helper.
   * @param \Drupal\hts\Service\HtsTermPathResolver $termPathResolver
   *   The term path resolver.
   */
  public function __construct(
    protected HtsOptionsBuilder $optionsBuilder,
    protected TermTreeHelper $termTreeHelper,
    protected HtsTermPathResolver $termPathResolver,
  ) {}

  /**
   * Filters options through the configured selection handler and term access.
   *
   * @param array<int|string, mixed> $options
   *   Raw options keyed by term ID.
   * @param array<string, mixed> $handler_settings
   *   Entity-reference selection handler settings.
   *
   * @return array{options: array<int|string, mixed>, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   Access-safe translated options and metadata.
   */
  public function accessSafeOptions(array $options, array $handler_settings): array {
    $resolver = $this->termPathResolver->forHandler($handler_settings);
    $cacheability = new CacheableMetadata();
    $resolved = [];
    foreach ($options as $tid => $label) {
      if ($tid === HtsConstants::NONE_VALUE) {
        $resolved[$tid] = $label;
        continue;
      }
      $result = $resolver->label((int) $tid);
      $cacheability = $cacheability->merge($result['cacheability']);
      if ($result['entity'] instanceof TermInterface) {
        $resolved[$tid] = $result['label'];
      }
    }
    return [
      'options' => $resolved,
      'cacheability' => $cacheability,
    ];
  }

  /**
   * Builds parent-keyed option maps for client-side cascade updates.
   *
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   * @param int $depth
   *   The number of hierarchy levels being built.
   *
   * @return array<int|string, array<int|string, list<array{value: string, label: string}>>>
   *   Option records keyed by level and then by parent term ID.
   */
  public function cascadeOptionMap(HtsBuildContext $context, int $depth): array {
    $map = [];
    $empty = [
      [
        'value' => HtsConstants::NONE_VALUE,
        'label' => (string) $this->optionsBuilder->getElementNoneLabel('0', $context->settings),
      ],
    ];

    $root = $this->accessSafeOptions(
      $this->optionsBuilder->getOptions('0', $context->handlerSettings),
      $context->handlerSettings,
    );
    $map['0'][''] = $this->jsOptions($root['options']);
    $map['0'][HtsConstants::NONE_VALUE] = $map['0'][''];

    for ($level = 1; $level < $depth; $level++) {
      $map[(string) $level][HtsConstants::NONE_VALUE] = $empty;
      foreach (array_keys($this->termTreeHelper->getTermsByLevel($level - 1, $context->handlerSettings['target_bundles'] ?? [])) as $parent_tid) {
        $children = $this->accessSafeOptions(
          $this->optionsBuilder->getChildrenOptions($parent_tid, (string) $level, $context->settings),
          $context->handlerSettings,
        );
        $map[(string) $level][(string) $parent_tid] = $this->jsOptions($children['options']);
      }
    }

    return $map;
  }

  /**
   * Builds term labels keyed by ID for updating the fallback autocomplete.
   *
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   * @param int $depth
   *   The number of hierarchy levels being built.
   *
   * @return array<string, string>
   *   Translated term labels keyed by term ID.
   */
  public function cascadeLabels(HtsBuildContext $context, int $depth): array {
    $labels = [];
    for ($level = 0; $level < $depth; $level++) {
      foreach (array_keys($this->termTreeHelper->getTermsByLevel($level, $context->handlerSettings['target_bundles'] ?? [])) as $tid) {
        $result = $this->termPathResolver->forHandler($context->handlerSettings)->label((int) $tid);
        if ($result['entity'] instanceof TermInterface) {
          $labels[(string) $tid] = (string) $result['label'];
        }
      }
    }
    return $labels;
  }

  /**
   * Converts render options into JavaScript-safe option records.
   *
   * @param array<int|string, mixed> $options
   *   Options keyed by term value with label text.
   *
   * @return list<array{value: string, label: string}>
   *   JavaScript-safe option records.
   */
  protected function jsOptions(array $options): array {
    $records = [];
    foreach ($options as $value => $label) {
      $records[] = [
        'value' => (string) $value,
        'label' => (string) $label,
      ];
    }
    return $records;
  }

}
