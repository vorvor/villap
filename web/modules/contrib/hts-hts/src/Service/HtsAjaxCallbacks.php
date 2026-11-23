<?php

namespace Drupal\hts\Service;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hts\HtsConstants;

/**
 * Handles the AJAX callbacks for the hierarchical term selector elements.
 */
class HtsAjaxCallbacks {

  /**
   * Constructs a HtsAjaxCallbacks object.
   */
  public function __construct(
    protected HtsSelectOptions $selectOptions,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Handles the AJAX callback for a plain select element.
   *
   * @param array<mixed, mixed> $form
   *   The complete form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array<string, mixed> $settings
   *   The plugin settings.
   * @param string $field_context
   *   The field-specific context string.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function selectCallback(array &$form, FormStateInterface $form_state, array $settings, string $field_context): AjaxResponse {
    $response = new AjaxResponse();
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || empty($triggering_element['#array_parents'])) {
      return $response;
    }

    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    $selected_level = (int) $triggering_element['#hts_level'];
    $delta = (string) $triggering_element['#hts_delta'];
    $depth = (int) ($triggering_element['#hts_depth'] ?? ($settings['hierarchy_depth'] ?? 0));
    $selected_tid = $triggering_element['#value'];
    $handler_settings = $triggering_element['#hts_handler_settings'] ?? [];
    $empty_selection = $this->isNone($selected_tid);

    for ($idx = $selected_level + 1; $idx < $depth; $idx++) {
      $element_parents = [...$parents, $idx];
      $next = NestedArray::getValue($form, $element_parents);
      if (!is_array($next)) {
        continue;
      }
      $next['#options'] = (!$empty_selection && $idx === $selected_level + 1)
        ? $this->selectOptions->childOptions($selected_tid, (string) $idx, $settings, $handler_settings)
        : [];
      $next['#value'] = HtsConstants::NONE_VALUE;
      $next['#default_value'] = HtsConstants::NONE_VALUE;
      $next['#attributes']['disabled'] = $empty_selection || $idx > $selected_level + 1;
      $response->addCommand(new ReplaceCommand(
        '#' . $this->selectOptions->elementIdByLevel($field_context, (string) $idx, $delta),
        $next,
      ));
    }

    $tid = $this->effectiveSelectedTid($selected_tid, $selected_level, $triggering_element, $form_state, $form, $parents);
    $selected = $this->selectOptions->autocompleteLabel($tid, $handler_settings);
    $response->addCommand(new InvokeCommand(
      'input#hts-autocomplete-' . $this->selectOptions->elementIdByDelta($field_context, $delta),
      'val',
      [$selected],
    ));

    return $response;
  }

  /**
   * Handles the AJAX callback for a Select2 element.
   *
   * Falls back to the plain callback when Select2 is unavailable.
   *
   * @param array<mixed, mixed> $form
   *   The complete form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array<string, mixed> $settings
   *   The plugin settings.
   * @param string $field_context
   *   The field-specific context string.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function select2Callback(array &$form, FormStateInterface $form_state, array $settings, string $field_context): AjaxResponse {
    if (!$this->moduleHandler->moduleExists('select2')) {
      return $this->selectCallback($form, $form_state, $settings, $field_context);
    }

    $response = new AjaxResponse();
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || NestedArray::getValue($form, $triggering_element['#array_parents']) === NULL) {
      return $response;
    }

    $selected_level = (int) $triggering_element['#hts_level'];
    $delta = (string) $triggering_element['#hts_delta'];
    $depth = (int) ($triggering_element['#hts_depth'] ?? ($settings['hierarchy_depth'] ?? 0));
    $selected_tid = $triggering_element['#value'];
    $handler_settings = $triggering_element['#hts_handler_settings'] ?? [];
    $empty_selection = $this->isNone($selected_tid);

    for ($idx = $selected_level + 1; $idx < $depth; $idx++) {
      $element_id = $this->selectOptions->elementIdByLevel($field_context, (string) $idx, $delta);
      $response->addCommand(new InvokeCommand('#' . $element_id, 'prop', ['disabled', TRUE]));
      $response->addCommand(new HtmlCommand('#' . $element_id, ''));
    }

    $next_level = $selected_level + 1;
    if (!$empty_selection && $next_level < $depth) {
      $element_id = $this->selectOptions->elementIdByLevel($field_context, (string) $next_level, $delta);
      $options = $this->selectOptions->childOptions($selected_tid, (string) $next_level, $settings, $handler_settings);
      $response->addCommand(new HtmlCommand('#' . $element_id, $this->selectOptions->buildOptionsHtml($options)));
      $response->addCommand(new InvokeCommand('#' . $element_id, 'prop', ['disabled', FALSE]));
    }

    $response->addCommand(new InvokeCommand(
      'input#hts-autocomplete-' . $this->selectOptions->elementIdByDelta($field_context, $delta),
      'val',
      [
        $this->selectOptions->autocompleteLabel(
          $this->effectiveSelectedTid($selected_tid, $selected_level, $triggering_element, $form_state),
          $handler_settings,
        ),
      ],
    ));

    return $response;
  }

  /**
   * Checks whether a submitted value represents the empty HTS sentinel.
   */
  private function isNone(mixed $value): bool {
    return $value === NULL || $value === '' || $value === HtsConstants::NONE_VALUE;
  }

  /**
   * Returns the term ID that should back the hidden autocomplete value.
   *
   * @param mixed $selected_tid
   *   The submitted value of the triggering element.
   * @param int $selected_level
   *   The hierarchy level of the triggering element.
   * @param array<string, mixed> $triggering_element
   *   The triggering form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array<mixed, mixed> $form
   *   The complete form.
   * @param array<int, mixed> $parents
   *   The array parents of the triggering element's container.
   *
   * @return mixed
   *   The effective term ID backing the autocomplete value.
   */
  private function effectiveSelectedTid(mixed $selected_tid, int $selected_level, array $triggering_element, FormStateInterface $form_state, array $form = [], array $parents = []): mixed {
    if (!$this->isNone($selected_tid) || $selected_level === 0) {
      return $selected_tid;
    }

    $input_parents = $triggering_element['#parents'] ?? [];
    if ($input_parents !== []) {
      array_pop($input_parents);
      $input = $form_state->getUserInput();
      $exists = FALSE;
      $previous = NestedArray::getValue($input, [...$input_parents, (string) ($selected_level - 1)], $exists);
      if ($exists && !$this->isNone($previous)) {
        return $previous;
      }
    }

    if ($parents !== []) {
      $previous_element = NestedArray::getValue($form, [...$parents, $selected_level - 1]);
      if (is_array($previous_element) && !$this->isNone($previous_element['#value'] ?? NULL)) {
        return $previous_element['#value'];
      }
    }

    return HtsConstants::NONE_VALUE;
  }

}
