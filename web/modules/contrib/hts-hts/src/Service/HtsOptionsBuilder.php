<?php

namespace Drupal\hts\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hts\HtsConstants;

/**
 * Builds options, labels and element identifiers for the term selector.
 */
class HtsOptionsBuilder {

  use StringTranslationTrait;

  /**
   * The taxonomy tree helper.
   *
   * @var \Drupal\hts\Service\TermTreeHelper
   */
  protected $termTreeHelper;

  /**
   * Constructs a HtsOptionsBuilder object.
   *
   * @param \Drupal\hts\Service\TermTreeHelper $term_tree_helper
   *   The taxonomy tree helper.
   */
  public function __construct(TermTreeHelper $term_tree_helper) {
    $this->termTreeHelper = $term_tree_helper;
  }

  /**
   * Returns the list of options for a level.
   *
   * @param string $level
   *   The hierarchy level.
   * @param array<string, mixed> $handler_settings
   *   The selection handler settings (must contain "target_bundles").
   *
   * @return array<int|string, mixed>
   *   The widget options keyed by term ID.
   */
  public function getOptions(string $level, array $handler_settings): array {
    $options = [];
    if (!isset($handler_settings['target_bundles'])) {
      return $options;
    }

    if ($level === '0') {
      $vocabulary_storage = $this->termTreeHelper->getVocabularyStorage();
      $parent_options = $vocabulary_storage->getToplevelTids($handler_settings['target_bundles']);
      foreach ($parent_options as $parent_tid) {
        $term = $this->termTreeHelper->getTerm((int) $parent_tid);
        if (!$term) {
          continue;
        }
        $options[$parent_tid]['name'] = $term->get('name')->getValue()[0]['value'];
        $options[$parent_tid]['weight'] = $term->get('weight')->getValue()[0]['value'];
      }
    }

    return $this->sortOptionsAlphabetically($options);
  }

  /**
   * Loads the term children by parent ID.
   *
   * @param string|int $parent_tid
   *   The parent taxonomy term ID.
   * @param string $level
   *   The hierarchy level.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return array<int|string, mixed>
   *   The sorted options.
   */
  public function getChildrenOptions($parent_tid, string $level = '', array $settings = []): array {
    if ($parent_tid === HtsConstants::NONE_VALUE) {
      return $this->sortOptionsAlphabetically($this->termTreeHelper->getTermsByLevel((int) $level, $settings['target_bundles']));
    }
    $children = $this->termTreeHelper->getTermStorage()->loadChildren((int) $parent_tid);
    $none = HtsConstants::NONE_VALUE;

    $options = [];
    $options[$none]['name'] = $this->getElementNoneLabel($level);
    $options[$none]['weight'] = -1000;

    foreach ($children as $child) {
      $tid = $child->id();
      $options[$tid]['name'] = $child->label();
      $options[$tid]['weight'] = $child->getWeight();
    }

    return $this->sortOptionsAlphabetically($options);
  }

  /**
   * Sorts an array of option data in alphabetical order.
   *
   * @param array<int|string, array<string, mixed>> $options
   *   The select options keyed by term ID, each with a "name" key.
   *
   * @return array<int|string, mixed>
   *   The sorted options keyed by term ID mapped to their name.
   */
  public function sortOptionsAlphabetically(array $options): array {
    asort($options);
    return array_filter(array_combine(array_keys($options), array_column($options, 'name')));
  }

  /**
   * Returns terms for a level, used for exposed views filters.
   *
   * @param string $level
   *   The hierarchy level.
   * @param array<int, string> $target_bundles
   *   The bundles to search.
   *
   * @return array<int|string, mixed>
   *   The sorted options keyed by term ID.
   */
  public function getViewOptions(string $level, array $target_bundles): array {
    return $this->sortOptionsAlphabetically($this->termTreeHelper->getTermsByLevel((int) $level, $target_bundles));
  }

  /**
   * Gets the element label by level.
   *
   * @param string $level
   *   The element hierarchy level.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return string|null
   *   The element label, or NULL.
   */
  public function getElementLabel(string $level, array $settings = []): ?string {
    return $this->filterPerLevel('label_level', $level, $settings, FALSE);
  }

  /**
   * Gets the element description by level.
   *
   * @param string $level
   *   The element hierarchy level.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return string|null
   *   The element description, or NULL.
   */
  public function getElementDescription(string $level, array $settings = []): ?string {
    return $this->filterPerLevel('description_level', $level, $settings, TRUE);
  }

  /**
   * Gets the "no selection" label by level.
   *
   * @param string $level
   *   The element hierarchy level.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return string|null
   *   The "no selection" label, or NULL.
   */
  public function getElementNoneLabel(string $level, array $settings = []): ?string {
    return $this->filterPerLevel('none_label', $level, $settings, FALSE);
  }

  /**
   * Returns the raw per-level setting value.
   *
   * @param string $setting
   *   The level setting name.
   * @param string $level
   *   The level index.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return string
   *   The setting value for the level.
   */
  public function getSettingPerLevel(string $setting, string $level, array $settings = []): string {
    $index = (int) $level;
    if ($setting === 'description_level') {
      $pieces = explode(PHP_EOL, $settings['description_level'] ?? '');
      return isset($pieces[$index]) ? trim($pieces[$index]) : '';
    }
    if ($setting === 'none_label') {
      $pieces = explode(PHP_EOL, $settings['none_label'] ?? '');
      if (isset($pieces[$index]) && trim($pieces[$index]) !== '') {
        return trim($pieces[$index]);
      }
      return (string) $this->t('@none_label', ['@none_label' => HtsConstants::NONE_LABEL]);
    }
    if ($setting === 'label_level') {
      $pieces = explode(',', $settings['label_level'] ?? '');
      return isset($pieces[$index]) ? trim($pieces[$index]) : '';
    }
    return '';
  }

  /**
   * Generates the wrapper element ID for a given delta.
   *
   * @param string $field_context
   *   A field-specific context string (field id or field name).
   * @param string $delta
   *   The element delta.
   *
   * @return string
   *   The generated element ID.
   */
  public function getElementIdByDelta(string $field_context, string $delta): string {
    return Html::getId($field_context . '-value-' . $delta);
  }

  /**
   * Generates the element ID for a given level.
   *
   * @param string $field_context
   *   A field-specific context string (field id or field name).
   * @param string $level
   *   The element hierarchy level.
   * @param string $delta
   *   The element delta.
   *
   * @return string
   *   The generated element ID.
   */
  public function getElementIdByLevel(string $field_context, string $level, string $delta): string {
    $field_id = $field_context . '__value-' . $delta . '__level-' . $level;
    return Html::getId('hts-select-element__' . $field_id);
  }

  /**
   * Filters and returns a per-level setting value.
   *
   * @param string $setting
   *   The level setting name.
   * @param string $level
   *   The level index.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   * @param bool $admin
   *   Whether to allow admin-level HTML.
   *
   * @return string|null
   *   The filtered value, or NULL when empty.
   */
  private function filterPerLevel(string $setting, string $level, array $settings, bool $admin): ?string {
    $value = $this->getSettingPerLevel($setting, $level, $settings);
    if ($value === '') {
      return NULL;
    }
    $filtered = $admin ? Xss::filterAdmin($value) : Xss::filter($value);
    return $filtered !== '' ? $filtered : NULL;
  }

}
