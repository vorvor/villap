<?php

namespace Drupal\hts\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hts\HierarchicalTermSelectorHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "hierarchical_term_selector" field widget.
 */
#[FieldWidget(
  id: 'hierarchical_term_selector',
  label: new TranslatableMarkup('Hierarchical Term Selector'),
  field_types: ['entity_reference'],
)]
class HierarchicalTermSelectorWidget extends EntityReferenceAutocompleteWidget {

  use HierarchicalTermSelectorHelper {
    defaultSettings as helperDefaultSettings;
    formElement as helperFormElement;
    settingsForm as helperSettingsForm;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setStringTranslation($container->get('string_translation'));
    $instance->htsSettingsFormBuilder = $container->get('hts.settings_form_builder');
    $instance->htsElementBuilder = $container->get('hts.element_builder');
    $instance->htsAjaxCallbacks = $container->get('hts.ajax_callbacks');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    if ($field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'taxonomy_term') {
      /* @see \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection */
      if (!empty($field_definition->getSettings()['handler_settings']['target_bundles'] ?? [])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The default settings.
   */
  public static function defaultSettings(): array {
    return static::helperDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The settings form.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return $form['element'] = $this->helperSettingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field values.
   * @param int $delta
   *   The order of this item in the array of subelements.
   * @param array<string, mixed> $element
   *   The form element.
   * @param array<mixed, mixed> $form
   *   The complete form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form element for a single widget.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var array<string, mixed> $hts_element */
    $hts_element = parent::formElement($items, $delta, $element, $form, $form_state);
    return $this->helperFormElement($hts_element);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The "hierarchical_term_selector" settings array.
   */
  protected function htsResolveSettings(): array {
    return $this->getSetting('hierarchical_term_selector') ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>|null
   *   The handler settings, or NULL.
   */
  protected function htsHandlerContext(): ?array {
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings') ?? [];
    $handler_settings['handler'] = $this->fieldDefinition->getSetting('handler') ?: 'default:taxonomy_term';
    return $handler_settings;
  }

  /**
   * {@inheritdoc}
   */
  protected function htsFieldContext(): ?string {
    $parts = [
      $this->fieldDefinition->getTargetEntityTypeId(),
      (string) $this->fieldDefinition->getTargetBundle(),
      $this->fieldDefinition->getName(),
    ];
    return str_replace('.', '_', implode('.', $parts));
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form the settings are included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The parent settings form.
   */
  protected function htsParentSettingsForm(array $form, FormStateInterface $form_state): array {
    return parent::settingsForm($form, $form_state);
  }

}
