<?php

namespace Drupal\villa_promenade_dashboard\Plugin\views\field;

use Drupal\villa_promenade_dashboard\FinancialQuery;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders the matching income or expense field with its native formatter.
 */
#[ViewsField('villa_financial_field')]
class FinancialField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    foreach (['income', 'expense'] as $type) {
      $dependencies['config'][] = 'field.storage.node.field_' . $type . '_' . $this->definition['financial_field'];
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $suffix = $this->definition['financial_field'];
    $this->field_alias = $this->query->addField(NULL, FinancialQuery::expression($this->query, $suffix), 'financial_' . $suffix);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $this->getEntity($values);
    if (!$node || !in_array($node->bundle(), ['income', 'expense'], TRUE)) {
      return [];
    }
    $suffix = $this->definition['financial_field'];
    $items = $node->get('field_' . $node->bundle() . '_' . $suffix);
    if ($suffix === 'date' && isset($this->definition['date_format']) && $items->isEmpty()) {
      return ['#plain_text' => $this->t('Undated'), '#access' => $items->access('view', NULL, TRUE)];
    }
    if ($suffix === 'currency' && $items->isEmpty()) {
      return ['#plain_text' => $this->t('Unspecified currency'), '#access' => $items->access('view', NULL, TRUE)];
    }
    $display = match ($suffix) {
      'amount' => ['type' => 'number_decimal', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 2, 'prefix_suffix' => FALSE]],
      'currency' => ['type' => 'list_default'],
      'date' => ['type' => 'datetime_custom', 'settings' => ['date_format' => $this->definition['date_format'] ?? 'Y-m-d', 'timezone_override' => '']],
      'category', 'user' => ['type' => 'entity_reference_label', 'settings' => ['link' => FALSE]],
      default => ['type' => 'basic_string'],
    };
    return $items->view($display + ['label' => 'hidden']);
  }

}
