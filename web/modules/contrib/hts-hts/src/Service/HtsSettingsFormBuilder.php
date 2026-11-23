<?php

namespace Drupal\hts\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds the settings form for the Hierarchical Term Selector.
 */
class HtsSettingsFormBuilder {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a HtsSettingsFormBuilder object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Builds the settings form.
   *
   * @param array<string, mixed> $element
   *   The element to attach the settings form to.
   * @param array<string, mixed> $settings
   *   The current settings for the "hierarchical_term_selector" sub-array.
   *
   * @return array<string, mixed>
   *   The settings form definition.
   */
  public function build(array $element, array $settings): array {
    $element['hierarchical_term_selector'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
      '#tree' => TRUE,
    ];

    $hts = &$element['hierarchical_term_selector'];
    $hts['hierarchy_depth'] = [
      '#min' => 0,
      '#type' => 'number',
      '#title' => $this->t('Hierarchy depth'),
      '#description' => $this->t('Limits the nesting level. Use 0 to display all values. For the hierarchy like "a" -> "b" -> "c" the selection of 2 will result in "b" being the deepest option.'),
      '#default_value' => $settings['hierarchy_depth'],
      '#required' => TRUE,
    ];
    $hts['label_level'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label per level'),
      '#description' => $this->t('Enter labels for each hierarchy-level separated by comma.'),
      '#default_value' => $settings['label_level'],
      '#required' => TRUE,
    ];
    $hts['description_level'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description per level'),
      '#default_value' => $settings['description_level'],
      '#description' => $this->t('Enter description per hierarchy-level separated by newline.'),
    ];
    $hts['none_label'] = [
      '#type' => 'textarea',
      '#title' => $this->t('The "no selection" label'),
      '#default_value' => $settings['none_label'],
      '#description' => $this->t('The label for an empty option per hierarchy-level separated by newline.'),
    ];

    $widget_options = ['select' => $this->t('Select')];
    if ($this->moduleHandler->moduleExists('select2')) {
      $widget_options['select2'] = $this->t('Select2');
    }
    $hts['widget_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget type'),
      '#options' => $widget_options,
      '#default_value' => $settings['widget_type'],
      '#description' => $this->t('Choose the type of widget to use for the hierarchical term selector.'),
    ];

    $hts['label_hide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide field label'),
      '#default_value' => $settings['label_hide'] ?? TRUE,
      '#description' => $this->t('Hides the field label from the display.'),
    ];

    return $element;
  }

}
