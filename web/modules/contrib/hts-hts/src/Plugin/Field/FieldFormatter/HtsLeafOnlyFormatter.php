<?php

namespace Drupal\hts\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hts\Service\HtsFormatterSupport;
use Drupal\hts\Service\HtsTermPathResolver;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders only selected terms without a selected descendant.
 *
 * @extends FormatterBase<FieldItemListInterface>
 */
#[FieldFormatter(
  id: 'hts_leaf_only',
  label: new TranslatableMarkup('HTS leaf-only list'),
  field_types: ['entity_reference'],
)]
final class HtsLeafOnlyFormatter extends FormatterBase {

  /**
   * Constructs the formatter.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array<string, mixed> $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array<string, mixed> $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\hts\Service\HtsFormatterSupport $support
   *   The formatter support service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected HtsFormatterSupport $support,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
    $instance = new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('hts.formatter_support'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The default settings.
   */
  public static function defaultSettings() {
    return [
      'multiple_parent_policy' => 'first',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [$this->t('Only selected terms without selected descendants are shown.')];
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array<int, mixed>
   *   A render array keyed by delta.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $selected = [];
    foreach ($items as $item) {
      $tid = (int) ($item->target_id ?? 0);
      if ($tid > 0) {
        $selected[] = $tid;
      }
    }

    $resolver = $this->resolver($langcode);
    $cacheability = new CacheableMetadata();
    $rendered = [];
    foreach (array_unique($selected) as $tid) {
      if (!$resolver->isLeafInSelection($tid, $selected) || !$this->entityTypeManager->getStorage('taxonomy_term')->load($tid)) {
        continue;
      }
      $result = $resolver->label($tid);
      $cacheability->addCacheableDependency($result['cacheability']);
      if ($result['entity'] instanceof TermInterface) {
        $rendered[] = $this->support->termLink($result['entity'], $cacheability, $result['label']);
      }
    }

    $elements = [];
    if ($rendered !== []) {
      $elements[0] = [
        '#theme' => 'item_list',
        '#items' => $rendered,
        '#attributes' => ['class' => ['hts-leaf-only']],
      ];
      $cacheability->applyTo($elements[0]);
    }
    else {
      $cacheability->applyTo($elements);
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'taxonomy_term';
  }

  /**
   * Returns a resolver configured from the field selection handler.
   */
  private function resolver(string $langcode): HtsTermPathResolver {
    return $this->support->resolverForField($this->fieldDefinition, $langcode);
  }

}
