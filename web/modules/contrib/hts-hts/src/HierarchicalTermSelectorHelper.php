<?php

namespace Drupal\hts;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;

/**
 * Wires the hierarchical term selector services into a host plugin.
 *
 * Provides the glue between a field widget or views filter plugin and the
 * stateless services that build the widget form, its options and AJAX
 * callbacks. Host classes must set the {@see $htsSettingsFormBuilder},
 * {@see $htsElementBuilder}, and {@see $htsAjaxCallbacks} properties in their
 * create() method.
 *
 * The trait targets two hosts with different plugin APIs: field widgets expose
 * getSetting() and a fieldDefinition, while views filters expose options only.
 * Host-specific access is isolated in the htsResolveSettings(),
 * htsHandlerContext() and htsFieldContext() seam methods, which the views
 * filter uses as-is and the field widget overrides.
 */
trait HierarchicalTermSelectorHelper {

  /**
   * The settings form builder.
   *
   * @var \Drupal\hts\Service\HtsSettingsFormBuilder
   */
  protected $htsSettingsFormBuilder;

  /**
   * The AJAX callback service.
   *
   * @var \Drupal\hts\Service\HtsAjaxCallbacks
   */
  protected $htsAjaxCallbacks;

  /**
   * The element builder.
   *
   * @var \Drupal\hts\Service\HtsElementBuilder
   */
  protected $htsElementBuilder;

  /**
   * Defines the default settings for this plugin.
   *
   * @return array<string, mixed>
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultSettings(): array {
    return [
      'hierarchical_term_selector' => [
        'hierarchy_depth' => 2,
        'label_level' => 'label1,label2',
        'description_level' => '',
        'none_label' => HtsConstants::NONE_LABEL,
        'label_hide' => 1,
        'widget_type' => 'select',
        'multiple_parent_policy' => 'first',
      ],
    ];
  }

  /**
   * Returns a form to configure settings.
   *
   * @param array<string, mixed> $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param bool $view
   *   Flag to check if coming from a view.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return array<string, mixed>
   *   The form definition for the settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state, $view = FALSE, array $settings = []): array {
    $element = $view ? [] : $this->htsParentSettingsForm($form, $form_state);
    if (empty($settings)) {
      $settings = $this->htsResolveSettings();
    }
    return $this->htsSettingsFormBuilder->build($element, $settings);
  }

  /**
   * Returns the form for a single widget.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param bool $view
   *   Flag to check if coming from a view.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   * @param array<int|string, mixed> $values
   *   The user input values.
   *
   * @return array<string, mixed>
   *   The form elements for a single widget.
   */
  public function formElement(array &$element, $view = FALSE, array $settings = [], array $values = []): array {
    if (empty($settings)) {
      $settings = $this->htsResolveSettings();
    }
    $handler_settings = $this->getHandlerSettings($view, $settings);
    return $this->htsElementBuilder->build($element, $this->getFieldContext($view), $settings, $handler_settings, $values, (bool) $view, $this);
  }

  /**
   * AJAX callback to update the selected options for a select element.
   *
   * @param array<mixed, mixed> $form
   *   The complete form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function selectElementOptionsCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    return $this->htsAjaxCallbacks->selectCallback($form, $form_state, $this->getCurrentSettings(), $this->getFieldContext($this->htsIsViewContext()));
  }

  /**
   * AJAX callback to update the next select2 element.
   *
   * @param array<mixed, mixed> $form
   *   The complete form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function select2ElementOptionsCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    return $this->htsAjaxCallbacks->select2Callback($form, $form_state, $this->getCurrentSettings(), $this->getFieldContext($this->htsIsViewContext()));
  }

  /**
   * Returns the current plugin settings.
   *
   * @return array<string, mixed>
   *   The "hierarchical_term_selector" settings array.
   */
  protected function getCurrentSettings(): array {
    return $this->htsResolveSettings();
  }

  /**
   * Resolves the selection handler settings for the current context.
   *
   * @param bool $view
   *   Flag to check if coming from a view.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return array<string, mixed>
   *   The handler settings, containing at least "target_bundles".
   */
  protected function getHandlerSettings($view, array $settings): array {
    $handler_settings = $view ? NULL : $this->htsHandlerContext();
    if ($handler_settings !== NULL) {
      return $handler_settings;
    }
    $settings['handler'] = $settings['handler'] ?? 'default:taxonomy_term';
    return $settings;
  }

  /**
   * Builds a field-specific context string used to generate element IDs.
   *
   * @param bool $view
   *   Flag to check if coming from a view.
   *
   * @return string
   *   The field context string.
   */
  protected function getFieldContext($view = FALSE): string {
    $context = $view ? NULL : $this->htsFieldContext();
    return $context ?? ($this->configuration['field_name'] ?? 'hts');
  }

  /**
   * Returns the "hierarchical_term_selector" settings from the host plugin.
   *
   * The views-filter default reads the stored plugin options. The field widget
   * overrides this to read the field-formatter setting.
   *
   * @return array<string, mixed>
   *   The current settings, or an empty array.
   */
  protected function htsResolveSettings(): array {
    return $this->options['hierarchical_term_selector'] ?? [];
  }

  /**
   * Returns the field-driven handler settings, or NULL when not field-based.
   *
   * The views-filter default is always NULL because a filter is not bound to a
   * field definition. The field widget overrides this with its field settings.
   *
   * @return array<string, mixed>|null
   *   The handler settings, or NULL.
   */
  protected function htsHandlerContext(): ?array {
    return NULL;
  }

  /**
   * Returns the field-driven element-ID context, or NULL when not field-based.
   *
   * The views-filter default is always NULL. The field widget overrides this
   * with its field id.
   *
   * @return string|null
   *   The context string, or NULL.
   */
  protected function htsFieldContext(): ?string {
    return NULL;
  }

  /**
   * Returns TRUE when the host builds an HTS Views exposed filter.
   */
  protected function htsIsViewContext(): bool {
    return FALSE;
  }

  /**
   * Returns the host plugin's own settings form to build HTS options onto.
   *
   * Views filters have no parent settings form at this stage and return an
   * empty array; the field widget returns its parent field-widget settings
   * form. Each host implements this because the relevant parent method differs
   * between the field-widget and views-filter plugin APIs.
   *
   * @param array<string, mixed> $form
   *   The form the settings are included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The parent settings form.
   */
  abstract protected function htsParentSettingsForm(array $form, FormStateInterface $form_state): array;

}
