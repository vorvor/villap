<?php

namespace Drupal\villa_promenade_dashboard\Plugin\views\filter;

use Drupal\villa_promenade_dashboard\FinancialQuery;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\EntityReference;

/**
 * Reuses the existing user autocomplete for both financial bundles.
 */
#[ViewsFilter('villa_balance_user')]
class BalanceUser extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $ids = array_values(array_filter(array_map('intval', (array) $this->value), static fn (int $id): bool => $id > 1));
    if (!$ids) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }
    $expression = FinancialQuery::expression($this->query, 'user');
    $placeholder = $this->placeholder() . '[]';
    $this->query->addWhereExpression($this->options['group'], $expression . ' IN (' . $placeholder . ')', [$placeholder => $ids]);
  }

}
