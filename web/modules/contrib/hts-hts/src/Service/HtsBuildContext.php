<?php

namespace Drupal\hts\Service;

/**
 * Immutable value object holding the context for building widget elements.
 */
final class HtsBuildContext {

  /**
   * The field-specific context string used to build element IDs.
   *
   * @var string
   */
  public $fieldContext;

  /**
   * The element delta.
   *
   * @var string
   */
  public $delta;

  /**
   * The resolved widget type ("select" or "select2").
   *
   * @var string
   */
  public $widgetType;

  /**
   * The current plugin settings.
   *
   * @var array<string, mixed>
   */
  public $settings;

  /**
   * The selection handler settings (must contain "target_bundles").
   *
   * @var array<string, mixed>
   */
  public $handlerSettings;

  /**
   * Whether the element is built for a views exposed filter.
   *
   * @var bool
   */
  public $view;

  /**
   * The plugin whose methods handle the AJAX callbacks.
   *
   * @var mixed
   */
  public $callbackOwner;

  /**
   * Constructs a HtsBuildContext object.
   *
   * @param string $field_context
   *   The field-specific context string.
   * @param string $delta
   *   The element delta.
   * @param string $widget_type
   *   The resolved widget type.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   * @param array<string, mixed> $handler_settings
   *   The selection handler settings.
   * @param bool $view
   *   Whether the element is built for a views exposed filter.
   * @param mixed $callback_owner
   *   The plugin whose methods handle the AJAX callbacks.
   */
  public function __construct(string $field_context, string $delta, string $widget_type, array $settings, array $handler_settings, bool $view, $callback_owner) {
    $this->fieldContext = $field_context;
    $this->delta = $delta;
    $this->widgetType = $widget_type;
    $this->settings = $settings;
    $this->handlerSettings = $handler_settings;
    $this->view = $view;
    $this->callbackOwner = $callback_owner;
  }

}
