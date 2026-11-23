<?php

namespace Drupal\hts\Service;

use Drupal\Component\Utility\Html;
use Drupal\hts\HtsConstants;
use Drupal\taxonomy\TermInterface;

/**
 * Resolves access-safe select and Select2 options for the AJAX callbacks.
 *
 * Owns the option-label resolution and Select2 markup building so the AJAX
 * callback service stays focused on assembling the AJAX response commands.
 */
class HtsSelectOptions {

  /**
   * Constructs the select options resolver.
   *
   * @param \Drupal\hts\Service\HtsOptionsBuilder $optionsBuilder
   *   The options builder.
   * @param \Drupal\hts\Service\HtsTermPathResolver $termPathResolver
   *   The term path resolver.
   */
  public function __construct(
    protected HtsOptionsBuilder $optionsBuilder,
    protected HtsTermPathResolver $termPathResolver,
  ) {}

  /**
   * Generates the wrapper element ID for a given delta.
   */
  public function elementIdByDelta(string $field_context, string $delta): string {
    return $this->optionsBuilder->getElementIdByDelta($field_context, $delta);
  }

  /**
   * Generates the element ID for a given level.
   */
  public function elementIdByLevel(string $field_context, string $level, string $delta): string {
    return $this->optionsBuilder->getElementIdByLevel($field_context, $level, $delta);
  }

  /**
   * Returns access-safe translated child options through the resolver.
   *
   * @param mixed $parent_tid
   *   The parent term ID or the "none" sentinel.
   * @param string $level
   *   The child hierarchy level.
   * @param array<string, mixed> $settings
   *   The plugin settings.
   * @param array<string, mixed> $handler_settings
   *   The selection handler settings.
   *
   * @return array<int|string, mixed>
   *   Options keyed by term ID with translated labels.
   */
  public function childOptions(mixed $parent_tid, string $level, array $settings, array $handler_settings): array {
    $raw_options = $this->optionsBuilder->getChildrenOptions($parent_tid, $level, $settings);
    $resolver = $this->termPathResolver->forHandler($handler_settings);
    $options = [];
    foreach ($raw_options as $tid => $label) {
      if ($tid === HtsConstants::NONE_VALUE) {
        $options[$tid] = $label;
        continue;
      }
      $result = $resolver->label((int) $tid);
      if ($result['entity'] instanceof TermInterface) {
        $options[$tid] = $result['label'];
      }
    }
    return $options;
  }

  /**
   * Returns the entity autocomplete value for an allowed translated term.
   *
   * @param mixed $tid
   *   The term ID or the "none" sentinel.
   * @param array<string, mixed> $handler_settings
   *   The selection handler settings.
   *
   * @return string|null
   *   The autocomplete value, or NULL when the term is not usable.
   */
  public function autocompleteLabel(mixed $tid, array $handler_settings): ?string {
    if ($tid === HtsConstants::NONE_VALUE || !is_numeric($tid)) {
      return NULL;
    }
    $result = $this->termPathResolver->forHandler($handler_settings)->label((int) $tid);
    $term = $result['entity'];
    return $term instanceof TermInterface
      ? (string) $result['label'] . ' (' . $term->id() . ')'
      : NULL;
  }

  /**
   * Builds escaped HTML option markup for a Select2 element.
   *
   * @param array<int|string, mixed> $options
   *   Options keyed by value with label text.
   *
   * @return string
   *   The escaped <option> markup.
   */
  public function buildOptionsHtml(array $options): string {
    $markup = '';
    foreach ($options as $key => $value) {
      $markup .= '<option value="' . Html::escape((string) $key) . '">' . Html::escape((string) $value) . '</option>';
    }
    return $markup;
  }

}
