<?php

namespace Drupal\villa_promenade_dashboard;

use Drupal\views\Plugin\views\query\Sql;

/**
 * Shared SQL expressions for financial fields stored on different bundles.
 */
final class FinancialQuery {

  /**
   * Returns one value per node from its income or expense field.
   */
  public static function expression(Sql $query, string $suffix): string {
    if (!in_array($suffix, ['date', 'amount', 'currency', 'description', 'notes', 'category', 'user'], TRUE)) {
      throw new \InvalidArgumentException('Unknown financial field.');
    }
    $column = in_array($suffix, ['category', 'user'], TRUE) ? 'target_id' : 'value';
    $fields = [];
    foreach (['income', 'expense'] as $type) {
      $field = 'field_' . $type . '_' . $suffix;
      $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', [
        'table' => 'node__' . $field,
        'field' => 'entity_id',
        'left_table' => 'node_field_data',
        'left_field' => 'nid',
        'type' => 'LEFT',
        'extra' => [
          ['field' => 'langcode', 'left_field' => 'langcode'],
          ['field' => 'delta', 'value' => 0],
          ['field' => 'deleted', 'value' => 0],
        ],
      ]);
      $alias = $query->ensureTable('node__' . $field, NULL, $join);
      $fields[] = $alias . '.' . $field . '_' . $column;
    }
    return 'COALESCE(' . implode(', ', $fields) . ')';
  }

}
