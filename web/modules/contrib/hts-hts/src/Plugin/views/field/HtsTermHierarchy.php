<?php

namespace Drupal\hts\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hts\Service\HtsFormatterSupport;
use Drupal\hts\Service\HtsTermPathResolver;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a taxonomy term as a deterministic first-parent hierarchy path.
 */
#[ViewsField('hts_term_hierarchy')]
class HtsTermHierarchy extends FieldPluginBase {

  /**
   * The term path resolver.
   *
   * @var \Drupal\hts\Service\HtsTermPathResolver
   */
  protected HtsTermPathResolver $termPathResolver;

  /**
   * The formatter support helper.
   *
   * @var \Drupal\hts\Service\HtsFormatterSupport
   */
  protected HtsFormatterSupport $support;

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
    $instance->termPathResolver = $container->get('hts.term_path_resolver');
    $instance->support = $container->get('hts.formatter_support');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The options definition.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['delimiter'] = ['default' => ' › '];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface|string {
    $tid = (int) $this->getValue($values);
    if ($tid <= 0 && $values->_entity instanceof TermInterface) {
      $tid = (int) $values->_entity->id();
    }
    if ($tid <= 0) {
      return '';
    }

    $resolver = $this->termPathResolver->forHandler([
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
    ]);
    $cacheability = new CacheableMetadata();
    $target = $resolver->label($tid);
    $cacheability->addCacheableDependency($target['cacheability']);
    if (!$target['entity'] instanceof TermInterface) {
      return $this->renderBuild([], $cacheability);
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hts-term-hierarchy']],
    ];
    $index = 0;
    foreach ($resolver->ancestors($tid, 'first') as $ancestor_tid) {
      $result = $resolver->label($ancestor_tid);
      $cacheability->addCacheableDependency($result['cacheability']);
      if (!$result['entity'] instanceof TermInterface) {
        continue;
      }
      if ($index > 0) {
        $build['separator_' . $index] = ['#plain_text' => $this->options['delimiter']];
      }
      $build['term_' . $index] = $this->support->termLink($result['entity'], $cacheability, $result['label']);
      $index++;
    }
    if ($index === 0) {
      $build = [];
    }
    return $this->renderBuild($build, $cacheability);
  }

  /**
   * Applies cacheability and renders the build to markup.
   *
   * @param array<string, mixed> $build
   *   The render array, possibly empty.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata to bubble.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered markup.
   */
  private function renderBuild(array $build, CacheableMetadata $cacheability): MarkupInterface|string {
    $cacheability->applyTo($build);
    return $this->getRenderer()->render($build);
  }

}
