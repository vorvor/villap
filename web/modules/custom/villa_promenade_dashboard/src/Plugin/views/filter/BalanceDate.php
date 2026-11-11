<?php

namespace Drupal\villa_promenade_dashboard\Plugin\views\filter;

use Drupal\villa_promenade_dashboard\FinancialQuery;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Applies inclusive calendar-date boundaries to both financial bundles.
 */
#[ViewsFilter('villa_balance_date')]
class BalanceDate extends ExpenseDate {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $value = (string) ($this->value['value'] ?? '');
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $value || !in_array($this->operator, ['>=', '<='], TRUE)) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }
    $expression = FinancialQuery::expression($this->query, 'date');
    $placeholder = $this->placeholder();
    $this->query->addWhereExpression($this->options['group'], $expression . ' ' . $this->operator . ' ' . $placeholder, [$placeholder => $value]);
  }

}
