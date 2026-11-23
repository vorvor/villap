<?php

namespace Drupal\hts\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hts\HtsConstants;
use Drupal\taxonomy\TermInterface;

/**
 * Builds hierarchical term selector form elements.
 */
class HtsElementBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a HtsElementBuilder object.
   */
  public function __construct(
    protected HtsOptionsBuilder $optionsBuilder,
    protected TermTreeHelper $termTreeHelper,
    protected HtsTermPathResolver $termPathResolver,
    protected ModuleHandlerInterface $moduleHandler,
    protected HtsCascadeMapBuilder $cascadeMapBuilder,
  ) {}

  /**
   * Builds the form for a single widget.
   *
   * @param array<string, mixed> $element
   *   The form element, passed by reference.
   * @param string $field_context
   *   The field-specific context string used to build element IDs.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   * @param array<string, mixed> $handler_settings
   *   Entity-reference selection handler settings.
   * @param array<int|string, mixed> $values
   *   The user input values.
   * @param bool $view
   *   Whether the element is built for a Views exposed filter.
   * @param mixed $callback_owner
   *   The plugin whose methods handle the AJAX callbacks.
   *
   * @return array<string, mixed>
   *   The form element for a single widget.
   */
  public function build(array &$element, string $field_context, array $settings, array $handler_settings, array $values, bool $view, $callback_owner): array {
    $autocomplete = $element['target_id'] ?? $element;
    $depth = (int) ($settings['hierarchy_depth'] ?? 0);
    if ($depth === 0) {
      $depth = $this->termTreeHelper->getMaximumDepth($handler_settings['target_bundles'] ?? []);
    }
    $settings['hierarchy_depth'] = $depth;
    $delta = (string) $autocomplete['#delta'];

    $default_value = ($autocomplete['#default_value'] ?? NULL) instanceof TermInterface
      ? (int) $autocomplete['#default_value']->id()
      : NULL;
    $parents = $this->getParents($default_value, $handler_settings);

    $element_id = $this->optionsBuilder->getElementIdByDelta($field_context, $delta);
    $this->hideAutocomplete($element, $element_id, $settings);
    $element['hierarchical_term_selector'] = [
      '#type' => 'container',
      '#prefix' => '<div class="hts-wrapper" id="hts-' . $element_id . '">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['hts-container']],
      'elements' => [],
    ];

    $context = new HtsBuildContext(
      $field_context,
      $delta,
      $this->resolveWidgetType($settings),
      $settings,
      $handler_settings,
      $view,
      $callback_owner,
    );
    $hts_elements = &$element['hierarchical_term_selector']['elements'];
    $this->buildChildren($hts_elements, $parents, $context, $depth);
    if ($view) {
      $this->attachCascadeSettings($element, $element_id, $context, $depth);
    }

    $last_parent = $parents ? end($parents) : NULL;
    $this->prepopulateChildOptions($hts_elements, $parents, $last_parent, $values, $context);
    return $element;
  }

  /**
   * Builds every configured child level, including unlimited depth.
   *
   * @param array<int, mixed> $hts_elements
   *   The level elements, keyed by level, passed by reference.
   * @param array<int, int> $parents
   *   First-parent term IDs from root to the default value.
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   * @param int $depth
   *   The number of hierarchy levels to build.
   */
  protected function buildChildren(array &$hts_elements, array $parents, HtsBuildContext $context, int $depth): void {
    for ($level = 0; $level < $depth; $level++) {
      $hts_elements[$level] = $this->buildLevelElement((string) $level, $parents, $context, $depth);
    }
  }

  /**
   * Hides the underlying autocomplete element.
   *
   * @param array<string, mixed> $element
   *   The form element, passed by reference.
   * @param string $element_id
   *   The generated wrapper element ID.
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   */
  protected function hideAutocomplete(array &$element, string $element_id, array $settings): void {
    $target = isset($element['target_id']) ? 'target_id' : NULL;
    $autocomplete = &$this->autocompleteReference($element, $target);
    $autocomplete['#title'] = $autocomplete['#title'] ?? $this->t('Select term');
    $autocomplete['#weight'] = -1;
    $autocomplete['#id'] = 'hts-autocomplete-' . $element_id;
    $autocomplete['#attributes']['class'][] = 'visually-hidden';
    if ($target !== NULL && !empty($settings['label_hide'])) {
      unset($autocomplete['#title']);
    }
  }

  /**
   * Returns a reference to the autocomplete portion of the element.
   *
   * @param array<string, mixed> $element
   *   The form element, passed by reference.
   * @param string|null $target
   *   The sub-element key holding the autocomplete, or NULL for the element.
   *
   * @return array<string, mixed>
   *   A reference to the autocomplete portion of the element.
   */
  protected function &autocompleteReference(array &$element, ?string $target): array {
    if ($target !== NULL) {
      return $element[$target];
    }
    return $element;
  }

  /**
   * Builds one hierarchy level.
   *
   * @param string $level
   *   The hierarchy level index.
   * @param array<int, int> $parents
   *   First-parent term IDs from root to the default value.
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   * @param int $depth
   *   The number of hierarchy levels being built.
   *
   * @return array<string, mixed>
   *   The render array for a single level element.
   */
  protected function buildLevelElement(string $level, array $parents, HtsBuildContext $context, int $depth): array {
    $level_index = (int) $level;
    $current_parent = $parents[$level_index] ?? NULL;
    $previous_parent = $level_index > 0 ? ($parents[$level_index - 1] ?? NULL) : NULL;
    $resolved = $this->resolveLevelOptions($level, $previous_parent, $context);
    $element_id = $this->optionsBuilder->getElementIdByLevel($context->fieldContext, $level, $context->delta);
    $base = [
      '#title' => $this->optionsBuilder->getElementLabel($level, $context->settings),
      '#options' => $resolved['options'],
      '#empty_option' => $this->optionsBuilder->getElementNoneLabel($level, $context->settings),
      '#description' => $this->optionsBuilder->getElementDescription($level, $context->settings),
      '#empty_value' => HtsConstants::NONE_VALUE,
      '#default_value' => $current_parent,
      '#hts_id' => $element_id,
      '#hts_level' => $level,
      '#hts_delta' => $context->delta,
      '#hts_depth' => $depth,
      '#hts_handler_settings' => $context->handlerSettings,
    ];
    if ((int) $level > 0 && !$previous_parent) {
      $base['#attributes']['disabled'] = 'disabled';
    }
    $resolved['cacheability']->applyTo($base);

    if ($context->widgetType === 'select2') {
      return $base + [
        '#type' => 'select2',
        '#id' => $element_id,
        '#attributes' => ['class' => ['select2-element', 'hts-select2']],
        '#attached' => ['library' => ['select2/select2']],
        '#ajax' => $this->buildAjax('select2ElementOptionsCallback', $element_id, $context->callbackOwner),
      ];
    }

    return $base + [
      '#type' => 'select',
      '#prefix' => '<div class="hts-select" id="' . $element_id . '">',
      '#suffix' => '</div>',
      '#wrapper_attributes' => ['class' => ['hts-select-container']],
      '#validated' => TRUE,
      '#ajax' => $this->buildAjax('selectElementOptionsCallback', $element_id, $context->callbackOwner),
    ];
  }

  /**
   * Resolves access-safe options and cacheability for one level.
   *
   * @param string $level
   *   The hierarchy level index.
   * @param mixed $previous_parent
   *   The parent term ID selected at the previous level, if any.
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   *
   * @return array{options: array<int|string, mixed>, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   Options and merged access/entity metadata.
   */
  protected function resolveLevelOptions(string $level, mixed $previous_parent, HtsBuildContext $context): array {
    if ($level === '0') {
      return $this->cascadeMapBuilder->accessSafeOptions(
        $this->optionsBuilder->getOptions($level, $context->handlerSettings),
        $context->handlerSettings,
      );
    }

    if ($previous_parent) {
      return $this->cascadeMapBuilder->accessSafeOptions(
        $this->optionsBuilder->getChildrenOptions($previous_parent, $level, $context->settings),
        $context->handlerSettings,
      );
    }

    return $this->cascadeMapBuilder->accessSafeOptions([
      HtsConstants::NONE_VALUE => $this->optionsBuilder->getElementNoneLabel($level, $context->settings),
    ], $context->handlerSettings);
  }

  /**
   * Attaches client-side cascading maps for Views exposed filters.
   *
   * @param array<string, mixed> $element
   *   The form element, passed by reference.
   * @param string $element_id
   *   The generated wrapper element ID.
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   * @param int $depth
   *   The number of hierarchy levels being built.
   */
  protected function attachCascadeSettings(array &$element, string $element_id, HtsBuildContext $context, int $depth): void {
    $config = [
      'autocomplete' => 'hts-autocomplete-' . $element_id,
      'noneValue' => HtsConstants::NONE_VALUE,
      'options' => $this->cascadeMapBuilder->cascadeOptionMap($context, $depth),
      'labels' => $this->cascadeMapBuilder->cascadeLabels($context, $depth),
    ];
    $element['hierarchical_term_selector']['#attributes']['data-hts-cascade'] = $element_id;
    $element['hierarchical_term_selector']['#attributes']['data-hts-config'] = json_encode($config, JSON_THROW_ON_ERROR);
    $element['#attached']['library'][] = 'hts/cascade';
  }

  /**
   * Builds an AJAX definition for a level element.
   *
   * @param string $callback
   *   The AJAX callback method name on the callback owner.
   * @param string $element_id
   *   The wrapper element ID the callback updates.
   * @param mixed $callback_owner
   *   The plugin whose methods handle the AJAX callbacks.
   *
   * @return array<string, mixed>
   *   The Drupal AJAX definition.
   */
  protected function buildAjax(string $callback, string $element_id, mixed $callback_owner): array {
    return [
      'callback' => [$callback_owner, $callback],
      'wrapper' => $element_id,
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Loading...'),
      ],
    ];
  }

  /**
   * Pre-populates next-level options from submitted values.
   *
   * @param array<int, mixed> $hts_elements
   *   The level elements, keyed by level, passed by reference.
   * @param array<int, int> $parents
   *   First-parent term IDs from root to the default value.
   * @param mixed $last_parent
   *   The deepest resolved parent term ID, or NULL.
   * @param array<int|string, mixed> $values
   *   The user input values keyed by level.
   * @param \Drupal\hts\Service\HtsBuildContext $context
   *   The shared build context.
   */
  protected function prepopulateChildOptions(array &$hts_elements, array $parents, mixed $last_parent, array $values, HtsBuildContext $context): void {
    foreach (array_keys($hts_elements) as $level) {
      $current_parent = $parents[$level] ?? NULL;
      $parent_matches = ($current_parent == $last_parent) || $context->view || ($last_parent === HtsConstants::NONE_VALUE) || !$last_parent;
      if (!$parent_matches || !isset($hts_elements[$level + 1], $values[$level])) {
        continue;
      }
      if ($values[$level] !== HtsConstants::NONE_VALUE) {
        $last_parent = $values[$level];
      }
      $resolved = $this->cascadeMapBuilder->accessSafeOptions(
        $this->optionsBuilder->getChildrenOptions($last_parent, (string) ($level + 1), $context->settings),
        $context->handlerSettings,
      );
      $hts_elements[$level + 1]['#options'] = $resolved['options'];
      $metadata = CacheableMetadata::createFromRenderArray($hts_elements[$level + 1])
        ->merge($resolved['cacheability']);
      $metadata->applyTo($hts_elements[$level + 1]);
    }
  }

  /**
   * Gets deterministic first-parent term IDs from root to requested term.
   *
   * @param mixed $tid
   *   The requested taxonomy term ID.
   * @param array<string, mixed> $handler_settings
   *   Entity-reference selection handler settings.
   *
   * @return array<int, int>
   *   First-parent term IDs including the requested term.
   */
  public function getParents(mixed $tid, array $handler_settings = []): array {
    if ($tid === NULL || !is_numeric($tid)) {
      return [];
    }
    return $this->termPathResolver
      ->forHandler($handler_settings)
      ->ancestors((int) $tid, 'first');
  }

  /**
   * Resolves the effective widget type, honoring Select2 availability.
   *
   * @param array<string, mixed> $settings
   *   The current plugin settings.
   *
   * @return string
   *   Either "select" or "select2".
   */
  protected function resolveWidgetType(array $settings): string {
    $widget_type = $settings['widget_type'] ?? 'select';
    if ($widget_type === 'select2' && !$this->moduleHandler->moduleExists('select2')) {
      return 'select';
    }
    return $widget_type;
  }

}
