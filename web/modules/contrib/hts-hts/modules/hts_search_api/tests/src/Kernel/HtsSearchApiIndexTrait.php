<?php

declare(strict_types=1);

namespace Drupal\Tests\hts_search_api\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;

/**
 * Builds the Search API server and index used by the HTS filter kernel tests.
 */
trait HtsSearchApiIndexTrait {

  /**
   * Creates the Search API server and index and indexes the test content.
   *
   * @param string $index_id
   *   The index id to create.
   */
  protected function createHtsIndex(string $index_id): void {
    Server::create([
      'id' => 'hts_test_server',
      'name' => 'HTS Test Server',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 1,
      ],
    ])->save();

    $index = Index::create([
      'id' => $index_id,
      'name' => 'HTS Test Index',
      'server' => 'hts_test_server',
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => ['index_directly' => TRUE],
    ]);
    $fields_helper = \Drupal::getContainer()->get('search_api.fields_helper');
    // Index the raw target ids of the term-reference field as integers. The
    // HTS Search API filter applies its resolved subtree ids as an IN
    // condition against exactly these values.
    $field = $fields_helper->createField($index, 'field_hts_terms', [
      'label' => 'HTS terms',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_hts_terms',
      'type' => 'integer',
    ]);
    $index->addField($field);
    $index->save();

    // Track every existing node, then index the tracked items so the backend
    // can answer the View query. Nodes were created before the index existed,
    // so tracking has to be triggered explicitly.
    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll($index);
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$index_id]);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $index_storage->load($index_id);
    $index->indexItems();
  }

}
