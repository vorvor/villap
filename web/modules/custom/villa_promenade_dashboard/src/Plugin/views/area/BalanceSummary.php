<?php

namespace Drupal\villa_promenade_dashboard\Plugin\views\area;

use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Displays a filtered financial summary without mixing currencies.
 */
#[ViewsArea('villa_balance')]
class BalanceSummary extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $build = [
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:expense', 'node_list:income'],
      ],
    ];
    // Match the financial listings, including unpublished internal records.
    if (!\Drupal::currentUser()->hasPermission('view villa financial reports')) {
      return $build;
    }

    $database = \Drupal::database();
    // Use the same built query as the listing so every active filter applies
    // identically to the totals, including the validated user autocomplete.
    $filtered = clone $this->view->build_info['query'];
    $filtered->range(NULL, NULL);
    $order = &$filtered->getOrderBy();
    $order = [];
    unset($order);
    $node_ids = $database->select($filtered, 'balance_filtered')->fields('balance_filtered', ['nid']);
    $transactions = NULL;
    foreach (['income', 'expense'] as $type) {
      $query = $database->select('node_field_data', 'n');
      $query->innerJoin('node__field_' . $type . '_amount', 'a', 'a.entity_id = n.nid AND a.langcode = n.langcode AND a.delta = 0 AND a.deleted = 0');
      $query->leftJoin('node__field_' . $type . '_currency', 'c', 'c.entity_id = n.nid AND c.langcode = n.langcode AND c.delta = 0 AND c.deleted = 0');
      $query->condition('n.type', $type);
      $query->condition('n.default_langcode', 1);
      $query->condition('n.nid', $node_ids, 'IN');
      $query->addExpression("COALESCE(c.field_{$type}_currency_value, '')", 'currency');
      $query->addExpression($type === 'income' ? 'a.field_income_amount_value' : '0', 'income');
      $query->addExpression($type === 'expense' ? 'a.field_expense_amount_value' : '0', 'expense');
      if ($transactions === NULL) {
        $transactions = $query;
      }
      else {
        $transactions->union($query, 'ALL');
      }
    }
    $totals = $database->select($transactions, 'transactions');
    $totals->addField('transactions', 'currency');
    $totals->addExpression('SUM(transactions.income)', 'income');
    $totals->addExpression('SUM(transactions.expense)', 'expense');
    $totals->addExpression('SUM(transactions.income - transactions.expense)', 'balance');
    $totals->groupBy('transactions.currency');

    $currencies = [
      'HUF' => ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00'],
      'EUR' => ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00'],
    ];
    foreach ($totals->execute() as $total) {
      $currencies[$total->currency] = (array) $total;
    }

    $rows = [];
    foreach ($currencies as $currency => $total) {
      $rows[] = [
        $currency !== '' ? $currency : $this->t('Unspecified currency'),
        ['data' => $this->formatAmount((string) $total['income']), 'class' => ['financial-balance__amount']],
        ['data' => $this->formatAmount((string) $total['expense']), 'class' => ['financial-balance__amount']],
        ['data' => $this->formatAmount((string) $total['balance']), 'class' => ['financial-balance__amount', str_starts_with((string) $total['balance'], '-') ? 'financial-balance__negative' : 'financial-balance__positive']],
      ];
    }
    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Totals for the selected filters, including unpublished records. Balance = income − expenses. Currencies are kept separate; no exchange-rate conversion is applied.'),
    ];
    $build['totals'] = [
      '#type' => 'table',
      '#caption' => $this->t('Income, expenses and net balance by currency'),
      '#header' => [$this->t('Currency'), $this->t('Total income'), $this->t('Total expenses'), $this->t('Net balance')],
      '#rows' => $rows,
      '#attributes' => ['class' => ['financial-balance']],
    ];
    if (isset($currencies[''])) {
      $build['notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Older records without a saved currency are listed separately. Edit those records to select HUF or EUR.'),
      ];
    }
    $build['#attached']['library'][] = 'villa_promenade_dashboard/balance';
    return $build;
  }

  /**
   * Formats SQL DECIMAL values without lossy floating-point conversion.
   */
  private function formatAmount(string $amount): string {
    $negative = str_starts_with($amount, '-');
    [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
    return ($negative ? '-' : '') . $whole . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
  }

}
