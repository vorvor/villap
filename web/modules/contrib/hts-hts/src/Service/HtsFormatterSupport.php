<?php

namespace Drupal\hts\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\taxonomy\TermInterface;
use Psr\Log\LoggerInterface;

/**
 * Shared term-path and link helpers for HTS formatters and Views fields.
 *
 * Centralizes the resolver configuration and the access-safe term link
 * building that the field formatters and the Views field would otherwise
 * duplicate.
 */
class HtsFormatterSupport {

  /**
   * Constructs the formatter support service.
   *
   * @param \Drupal\hts\Service\HtsTermPathResolver $termPathResolver
   *   The term path resolver.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Psr\Log\LoggerInterface $logger
   *   The HTS logger channel.
   */
  public function __construct(
    protected HtsTermPathResolver $termPathResolver,
    protected EntityRepositoryInterface $entityRepository,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Returns a resolver configured from a field selection handler.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition whose selection handler drives the resolver.
   * @param string $langcode
   *   The active langcode.
   *
   * @return \Drupal\hts\Service\HtsTermPathResolver
   *   A resolver bound to the field's selection handler.
   */
  public function resolverForField(FieldDefinitionInterface $field_definition, string $langcode): HtsTermPathResolver {
    $settings = $field_definition->getSetting('handler_settings') ?? [];
    $settings['handler'] = $field_definition->getSetting('handler') ?: 'default:taxonomy_term';
    $settings['langcode'] = $langcode;
    return $this->termPathResolver->forHandler($settings);
  }

  /**
   * Builds an access-safe translated term link or plain label render array.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term to render, already loaded via the resolver.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata to enrich with the URL access result.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $label
   *   An already-resolved translated label, or NULL to translate here.
   *
   * @return array<string, mixed>
   *   A "#type => link" or "#plain_text" render array.
   */
  public function termLink(TermInterface $term, CacheableMetadata $cacheability, mixed $label = NULL): array {
    $term = $this->entityRepository->getTranslationFromContext($term);
    if ($label === NULL) {
      $label = $term->label() ?? '';
    }
    try {
      $url = $term->toUrl();
      $access = $url->access(return_as_object: TRUE);
      $cacheability->addCacheableDependency($access);
      if ($access->isAllowed()) {
        return [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $url,
        ];
      }
    }
    catch (UndefinedLinkTemplateException $e) {
      $this->logger->debug('Term @tid has no canonical URL template: @message', [
        '@tid' => $term->id(),
        '@message' => $e->getMessage(),
      ]);
    }
    return ['#plain_text' => $label];
  }

}
