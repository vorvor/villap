<?php

namespace Drupal\hts\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hts\Service\HtsFormatterSupport;
use Drupal\hts\Service\HtsTermPathResolver;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders referenced terms as a nested hierarchy.
 *
 * @extends FormatterBase<FieldItemListInterface>
 */
#[FieldFormatter(
  id: 'hts_hierarchical_list',
  label: new TranslatableMarkup('HTS hierarchical list'),
  field_types: ['entity_reference'],
)]
final class HtsHierarchicalListFormatter extends FormatterBase {

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
      'multiple_parent_policy' => 'all',
    ] + parent::defaultSettings();
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
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
      'multiple_parent_policy' => [
        '#type' => 'select',
        '#title' => $this->t('Multiple-parent policy'),
        '#options' => [
          'first' => $this->t('First parent'),
          'all' => $this->t('All parents'),
        ],
        '#default_value' => $this->getSetting('multiple_parent_policy'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [$this->t('Multiple-parent policy: @policy', ['@policy' => $this->getSetting('multiple_parent_policy')])];
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
    $resolver = $this->resolver($langcode);
    $policy = $this->getSetting('multiple_parent_policy') === 'first' ? 'first' : 'all';
    $cacheability = new CacheableMetadata();
    $tree = $this->buildTree($items, $resolver, $policy, $cacheability);

    $elements = [];
    if ($tree !== []) {
      $elements[0] = [
        '#theme' => 'hts_hierarchical_list',
        '#items' => array_values($tree),
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
   * Builds the nested render tree of accessible term links.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The referenced term items.
   * @param \Drupal\hts\Service\HtsTermPathResolver $resolver
   *   The configured resolver.
   * @param string $policy
   *   The multiple-parent policy, "first" or "all".
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata collected while resolving terms.
   *
   * @return array<int, array{link: array<string, mixed>, children: array<int, mixed>}>
   *   The nested tree keyed by term ID.
   */
  private function buildTree(FieldItemListInterface $items, HtsTermPathResolver $resolver, string $policy, CacheableMetadata $cacheability): array {
    $tree = [];
    foreach ($items as $item) {
      $tid = (int) ($item->target_id ?? 0);
      if ($tid <= 0 || !$this->entityTypeManager->getStorage('taxonomy_term')->load($tid)) {
        continue;
      }
      foreach ($resolver->paths($tid, $policy) as $path) {
        $this->graftPath($tree, $path, $resolver, $cacheability);
      }
    }
    return $tree;
  }

  /**
   * Grafts one root-to-term path onto the tree.
   *
   * @param array<int, array{link: array<string, mixed>, children: array<int, mixed>}> $tree
   *   The tree to graft onto, passed by reference.
   * @param array<int> $path
   *   A root-to-term path of term IDs.
   * @param \Drupal\hts\Service\HtsTermPathResolver $resolver
   *   The configured resolver.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata collected while resolving terms.
   */
  private function graftPath(array &$tree, array $path, HtsTermPathResolver $resolver, CacheableMetadata $cacheability): void {
    $branch = &$tree;
    foreach ($path as $path_tid) {
      $result = $resolver->label($path_tid);
      $cacheability->addCacheableDependency($result['cacheability']);
      if (!$result['entity'] instanceof TermInterface) {
        continue;
      }
      if (!isset($branch[$path_tid])) {
        $branch[$path_tid] = [
          'link' => $this->support->termLink($result['entity'], $cacheability, $result['label']),
          'children' => [],
        ];
      }
      $branch = &$branch[$path_tid]['children'];
    }
    unset($branch);
  }

  /**
   * Returns a resolver configured from the field selection handler.
   */
  private function resolver(string $langcode): HtsTermPathResolver {
    return $this->support->resolverForField($this->fieldDefinition, $langcode);
  }

}
