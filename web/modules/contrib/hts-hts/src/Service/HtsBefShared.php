<?php

namespace Drupal\hts\Service;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hts\HtsConstants;

/**
 * Provides BEF-version-neutral helpers for HTS exposed filters.
 */
class HtsBefShared {

  /**
   * Checks whether a Views filter is the exposed HTS filter.
   *
   * @param object|null $filter
   *   A Views filter plugin.
   * @param array<string, mixed> $filter_options
   *   Filter options supplied by Better Exposed Filters.
   *
   * @return bool
   *   TRUE when the HTS BEF widget applies.
   */
  public function isApplicable(?object $filter, array $filter_options = []): bool {
    if ($filter === NULL || !method_exists($filter, 'getPluginId')) {
      return FALSE;
    }
    $type = $filter_options['type'] ?? ($filter->options['type'] ?? NULL);
    $plugin_id = $filter->getPluginId();
    $is_hts_class = is_a($filter, 'Drupal\\hts\\Plugin\\views\\filter\\HtsTaxonomyIndexTid');
    return ($plugin_id === 'hts_taxonomy_index_tid' || $is_hts_class) && $type === 'hts';
  }

  /**
   * Extracts selected term IDs from one exposed identifier value only.
   *
   * @param mixed $value
   *   The value stored under the configured exposed identifier.
   *
   * @return array<int>
   *   Selected term IDs in input order.
   */
  public function selectedTermIds(mixed $value): array {
    $selected = [];
    $this->collectTermIds($value, $selected);
    return array_values(array_unique($selected));
  }

  /**
   * Marks the active HTS exposed element for BEF presentation.
   *
   * @param array<mixed, mixed> $form
   *   The exposed form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $identifier
   *   The configured exposed identifier.
   */
  public function alterExposedForm(array &$form, FormStateInterface $form_state, string $identifier): void {
    $element = &$form[$identifier];
    if (!isset($element) && isset($form[$identifier . '_wrapper'][$identifier])) {
      $element = &$form[$identifier . '_wrapper'][$identifier];
    }
    if (!isset($element)) {
      return;
    }
    // The exposed element is a bare form tree without a render wrapper, so its
    // own #attributes are never emitted. Wrap it in a container so the marker
    // class identifying the BEF-processed HTS filter is rendered.
    $element['#theme_wrappers'][] = 'container';
    $element['#attributes']['class'][] = 'hts-bef';
    $element['#hts_input'] = $this->selectedTermIds($form_state->getUserInput()[$identifier] ?? []);
  }

  /**
   * Recursively collects scalar taxonomy term IDs.
   *
   * @param mixed $value
   *   Input value.
   * @param array<int> $selected
   *   Collected IDs.
   */
  private function collectTermIds(mixed $value, array &$selected): void {
    if (is_array($value)) {
      foreach ($value as $child) {
        $this->collectTermIds($child, $selected);
      }
      return;
    }
    $tid = $this->normalizeTermId($value);
    if ($tid !== NULL) {
      $selected[] = $tid;
    }
  }

  /**
   * Normalizes a scalar input value to a positive term ID.
   *
   * @param mixed $value
   *   A scalar input value.
   *
   * @return int|null
   *   The positive term ID, or NULL when the value is not a usable ID.
   */
  private function normalizeTermId(mixed $value): ?int {
    if ($value === NULL || $value === '' || $value === HtsConstants::NONE_VALUE) {
      return NULL;
    }
    $digits = $this->extractDigits($value);
    if ($digits === NULL) {
      return NULL;
    }
    $tid = (int) $digits;
    return $tid > 0 ? $tid : NULL;
  }

  /**
   * Extracts an integer-like digit sequence from a scalar input value.
   *
   * Accepts an integer, a digit-only string, or an entity-autocomplete label
   * that ends in "(123)".
   *
   * @param mixed $value
   *   A scalar input value.
   *
   * @return string|null
   *   The digit sequence, or NULL when the value is not a usable ID.
   */
  private function extractDigits(mixed $value): ?string {
    if (is_int($value)) {
      return (string) $value;
    }
    if (!is_string($value)) {
      return NULL;
    }
    if (preg_match('/\((\d+)\)\s*$/', $value, $matches)) {
      return $matches[1];
    }
    return ctype_digit($value) ? $value : NULL;
  }

}
