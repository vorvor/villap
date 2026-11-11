<?php

namespace Drupal\villa_promenade_dashboard\Plugin\views\sort;

use Drupal\villa_promenade_dashboard\FinancialQuery;
use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sorts the combined ledger by the actual income or expense date.
 */
#[ViewsSort('villa_financial_date')]
class FinancialDate extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->query->addOrderBy(NULL, FinancialQuery::expression($this->query, 'date'), $this->options['order'], 'financial_date_sort');
  }

}
