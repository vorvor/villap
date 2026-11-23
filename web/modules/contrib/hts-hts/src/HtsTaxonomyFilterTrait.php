<?php

namespace Drupal\hts;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hts\Service\HtsBefShared;
use Drupal\hts\Service\HtsTermPathResolver;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shares the HTS cascading term-selector logic across Views filter plugins.
 *
 * The SQL-backed {@see \Drupal\hts\Plugin\views\filter\HtsTaxonomyIndexTid} and
 * the Search API-backed
 * {@see \Drupal\hts_search_api\Plugin\views\filter\HtsSearchApiTerm} render the
 * same cascading exposed selector and resolve the chosen path to a concrete set
 * of term ids. The only difference is how the resolved value is applied to the
 * query, which each parent class handles: the SQL filter joins on the term
 * table, while the Search API filter turns the value into an IN condition via
 * the Search API filter trait's opHelper().
 *
 * The trait reuses {@see HierarchicalTermSelectorHelper} for the actual widget
 * building, aliasing its defaultSettings(), formElement() and settingsForm()
 * methods the same way the field widget does. Host classes only need to call
 * {@see self::htsAssignFilterServices()} from their own create() method, since
 * create() signatures and parent calls differ per parent class.
 */
trait HtsTaxonomyFilterTrait {

  use HierarchicalTermSelectorHelper {
    defaultSettings as helperDefaultSettings;
    formElement as helperFormElement;
    settingsForm as helperSettingsForm;
  }

  /**
   * Stores the validated exposed input for this filter.
   *
   * @var array<int|string, mixed>|null
   */
  public $validatedExposedInput = NULL;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The term path resolver.
   *
   * @var \Drupal\hts\Service\HtsTermPathResolver
   */
  protected HtsTermPathResolver $termPathResolver;

  /**
   * BEF-neutral input helpers.
   *
   * @var \Drupal\hts\Service\HtsBefShared
   */
  protected HtsBefShared $htsBefShared;

  /**
   * Cacheability discovered while resolving selected terms.
   */
  protected CacheableMetadata $resolverCacheability;

  /**
   * Assigns the HTS services shared by every HTS Views filter plugin.
   *
   * Host plugins call this from their own create() after invoking their
   * parent::create(), because the parent create() chain differs per plugin
   * (core taxonomy filter versus Search API term filter).
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  protected function htsAssignFilterServices(ContainerInterface $container): void {
    $this->entityRepository = $container->get('entity.repository');
    $this->setStringTranslation($container->get('string_translation'));
    $this->htsSettingsFormBuilder = $container->get('hts.settings_form_builder');
    $this->htsElementBuilder = $container->get('hts.element_builder');
    $this->htsAjaxCallbacks = $container->get('hts.ajax_callbacks');
    $this->termPathResolver = $container->get('hts.term_path_resolver');
    $this->htsBefShared = $container->get('hts.bef_shared');
    $this->resolverCacheability = new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The options definition.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $defaults = static::helperDefaultSettings();
    $options['hierarchical_term_selector'] = [
      'default' => $defaults['hierarchical_term_selector'],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int|string, mixed>|null
   *   The value options.
   */
  public function getValueOptions() {
    return $this->valueOptions;
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
   *   The parent settings form.
   */
  protected function htsParentSettingsForm(array $form, FormStateInterface $form_state): array {
    // A views filter has no separate settings form at this stage; the HTS
    // options are attached to the extra-options form instead.
    return $form['hierarchical_term_selector'] ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed, mixed> $form
   *   The extra options form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildExtraOptionsForm($form, $form_state);
    $form['type']['#options']['hts'] = $this->t('Hierarchical Term Selector');
    $settings = $this->options['hierarchical_term_selector'] ?? static::helperDefaultSettings()['hierarchical_term_selector'];
    $form += $this->helperSettingsForm($form, $form_state, TRUE, $settings);
    $form['hierarchical_term_selector']['#states'] = [
      'visible' => [
        ':input[name="options[type]"]' => ['value' => 'hts'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed, mixed> $form
   *   The exposed form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposedForm($form, $form_state);
    if (!$this->isHtsExposed() || empty($this->options['expose']['identifier'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];
    $user_input = $form_state->getUserInput();
    if (isset($user_input[$identifier]) && !is_array($user_input[$identifier])) {
      $user_input[$identifier] = [];
      $form_state->setUserInput($user_input);
    }
    $input = $form_state->getUserInput()[$identifier] ?? [];
    $selected = $this->selectedTermIdsFromInput($input);
    $handler_settings = $this->selectionSettings();
    $resolver = $this->termPathResolver->forHandler($handler_settings);
    $default_term = NULL;
    $values = [];
    if ($selected !== []) {
      $tid = (int) end($selected);
      $values = $resolver->ancestors($tid, 'first');
      $result = $resolver->label($tid);
      $this->mergeResolverCacheability($result['cacheability']);
      $default_term = $result['entity'];
    }

    $selector_settings = $handler_settings + [
      'sort' => [
        'field' => 'name',
        'direction' => 'ASC',
      ],
      'auto_create' => FALSE,
      'auto_create_bundle' => NULL,
      'match_limit' => 10,
    ];
    $settings = array_merge($this->options['hierarchical_term_selector'], $selector_settings);
    $element = [
      'target_id' => [
        '#title' => $this->options['limit']
          ? $this->t('Select terms from vocabulary @voc', ['@voc' => $this->options['vid']])
          : $this->t('Select terms'),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#selection_handler' => $selector_settings['handler'],
        '#selection_settings' => $selector_settings,
        '#tags' => TRUE,
        '#default_value' => $default_term instanceof TermInterface ? $default_term : NULL,
        '#delta' => 0,
      ],
    ];
    $replacement = $this->helperFormElement($element, TRUE, $settings, $values);
    // Force the replacement into a form tree rooted at the exposed
    // identifier so its level selects and autocomplete submit as nested
    // input under the identifier instead of bare top-level GET params
    // (0, 1, target_id). Without this the exposed filter renders but
    // never narrows results, because applyIdentifierInput() only reads
    // $input[<identifier>].
    $replacement['#tree'] = TRUE;
    $replacement['#parents'] = [$identifier];

    if (isset($form[$identifier . '_wrapper'][$identifier])) {
      $form[$identifier . '_wrapper'][$identifier] = $replacement;
    }
    else {
      $form[$identifier] = $replacement;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed, mixed> $form
   *   The exposed form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateExposed(&$form, FormStateInterface $form_state): void {
    parent::validateExposed($form, $form_state);
    if (!$this->isHtsExposed()) {
      return;
    }
    $this->applyIdentifierInput($form_state->getUserInput());
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int|string, mixed> $input
   *   The exposed input.
   */
  public function acceptExposedInput($input) {
    if (!$this->isHtsExposed()) {
      return parent::acceptExposedInput($input);
    }
    if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return TRUE;
    }
    $this->applyIdentifierInput($input);
    $this->validatedExposedInput = $this->value;
    return !empty($this->value);
  }

  /**
   * Applies only the configured exposed identifier to the filter value.
   *
   * The resolver expands the deepest selection to its whole subtree and stores
   * every resulting term id in $this->value. For the SQL filter the parent join
   * matches those ids directly; for the Search API filter opHelper() turns the
   * same ids into an IN condition. Because the subtree is expanded here, the
   * Search API integration does NOT require the AddHierarchy processor to be
   * enabled on the index.
   *
   * @param array<int|string, mixed> $input
   *   The exposed input.
   */
  private function applyIdentifierInput(array $input): void {
    $identifier = $this->options['expose']['identifier'];
    $selected = $this->selectedTermIdsFromInput($input[$identifier] ?? []);
    if ($selected === []) {
      $this->value = [];
      return;
    }

    // The cascade submits every chosen level (e.g. continent, country, city);
    // filter by the deepest (most specific) selection only, then expand its
    // subtree. Passing every level would union each level's subtree and match
    // siblings under a shared ancestor.
    $resolved = $this->termPathResolver
      ->forHandler($this->selectionSettings())
      ->resolveSelection([(int) end($selected)], TRUE);
    $this->value = $resolved['ids'];
    $this->mergeResolverCacheability($resolved['cacheability']);
  }

  /**
   * Extracts the submitted HTS term IDs, preferring cascade levels.
   *
   * A stale entity-autocomplete value can remain in query strings when a user
   * resets the visible cascade to "none". When cascade elements are present,
   * they are the canonical exposed-filter input.
   *
   * @param mixed $input
   *   The exposed input under this filter's identifier.
   *
   * @return int[]
   *   Selected term IDs in input order.
   */
  private function selectedTermIdsFromInput(mixed $input): array {
    if (is_array($input) && array_key_exists('hierarchical_term_selector', $input)) {
      return $this->htsBefShared->selectedTermIds($input['hierarchical_term_selector']['elements'] ?? []);
    }
    return $this->htsBefShared->selectedTermIds($input);
  }

  /**
   * Returns selection-handler configuration for the selected vocabulary.
   *
   * @return array<string, mixed>
   *   The selection-handler configuration.
   */
  private function selectionSettings(): array {
    $vid = $this->options['vid'] ?? NULL;
    return [
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
      'target_bundles' => $vid ? [$vid => $vid] : NULL,
    ];
  }

  /**
   * Checks whether the filter is the exposed HTS variant.
   */
  private function isHtsExposed(): bool {
    return ($this->options['type'] ?? NULL) === 'hts' && $this->isExposed();
  }

  /**
   * {@inheritdoc}
   */
  protected function htsIsViewContext(): bool {
    return TRUE;
  }

  /**
   * Merges resolver metadata into Views cacheability methods.
   */
  private function mergeResolverCacheability(CacheableMetadata $cacheability): void {
    $this->resolverCacheability = ($this->resolverCacheability ?? new CacheableMetadata())->merge($cacheability);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed, mixed> $form
   *   The expose form, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposeForm($form, $form_state);
    if (($this->options['type'] ?? NULL) !== 'hts') {
      unset($form['expose']['reduce']);
    }
    $form['error_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display error message'),
      '#default_value' => !empty($this->options['error_message']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    $this->valueOptions = [];
    if ($this->value) {
      $terms = $this->termStorage->loadMultiple(array_filter($this->value));
      foreach ($terms as $term) {
        $translated = $this->entityRepository->getTranslationFromContext($term);
        $this->valueOptions[$term->id()] = $translated->label();
      }
    }
    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      array_merge(['languages:language_interface'], ($this->resolverCacheability ?? new CacheableMetadata())->getCacheContexts()),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      ($this->resolverCacheability ?? new CacheableMetadata())->getCacheTags(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::mergeMaxAges(
      parent::getCacheMaxAge(),
      ($this->resolverCacheability ?? new CacheableMetadata())->getCacheMaxAge(),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The calculated dependencies.
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $vocabulary = $this->vocabularyStorage->load($this->options['vid']);
    if ($vocabulary) {
      $dependencies[$vocabulary->getConfigDependencyKey()][] = $vocabulary->getConfigDependencyName();
    }
    foreach ($this->termStorage->loadMultiple($this->options['value'] ?? []) as $term) {
      $dependencies[$term->getConfigDependencyKey()][] = $term->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The extra options form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitExtraOptionsForm($form, FormStateInterface $form_state): void {
    parent::submitExtraOptionsForm($form, $form_state);
    $values = $form_state->getValues();
    if (isset($values['options']['hierarchical_term_selector']['hierarchical_term_selector'])) {
      $this->options['hierarchical_term_selector'] = $values['options']['hierarchical_term_selector']['hierarchical_term_selector'];
    }
  }

}
