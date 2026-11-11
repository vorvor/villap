<?php

namespace Drupal\villa_promenade_dashboard;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;

/**
 * Builds filtered monthly comparisons and income-versus-expense pie charts.
 */
final class BalanceCharts {

  use StringTranslationTrait;

  /**
   * Builds one independent overview for every currency in the ledger.
   */
  public function build(ViewExecutable $view): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['balance-charts']],
      '#attached' => ['library' => ['villa_promenade_dashboard/balance-charts']],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user.permissions', 'url.query_args'],
        'tags' => ['node_list:income', 'node_list:expense'],
      ],
    ];
    if (!\Drupal::currentUser()->hasPermission('view villa financial reports')) {
      $build['#access'] = FALSE;
      return $build;
    }
    $build['heading'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Balance overview')];
    $build['intro'] = [
      '#type' => 'html_tag', '#tag' => 'p',
      '#value' => $this->t('Charts use all transactions matching the current list filters, including unpublished and undated records, not just the current page. Currencies are never combined.'),
    ];

    $database = \Drupal::database();
    $filtered = clone $view->build_info['query'];
    $filtered->range(NULL, NULL);
    $order = &$filtered->getOrderBy();
    $order = [];
    unset($order);
    $node_ids = $database->select($filtered, 'filtered_ledger')->fields('filtered_ledger', ['nid']);
    $transactions = NULL;
    foreach (['income', 'expense'] as $type) {
      $query = $database->select('node_field_data', 'n');
      $query->innerJoin('node__field_' . $type . '_amount', 'a', 'a.entity_id = n.nid AND a.langcode = n.langcode AND a.delta = 0 AND a.deleted = 0');
      $query->leftJoin('node__field_' . $type . '_currency', 'c', 'c.entity_id = n.nid AND c.langcode = n.langcode AND c.delta = 0 AND c.deleted = 0');
      $query->leftJoin('node__field_' . $type . '_date', 'd', 'd.entity_id = n.nid AND d.langcode = n.langcode AND d.delta = 0 AND d.deleted = 0');
      $query->condition('n.type', $type)->condition('n.default_langcode', 1)->condition('n.nid', $node_ids, 'IN');
      $query->addExpression("COALESCE(c.field_{$type}_currency_value, '')", 'currency');
      $query->addExpression("COALESCE(d.field_{$type}_date_value, '')", 'transaction_date');
      $query->addExpression($type === 'income' ? 'a.field_income_amount_value' : '0', 'income');
      $query->addExpression($type === 'expense' ? 'a.field_expense_amount_value' : '0', 'expense');
      if ($transactions === NULL) {
        $transactions = $query;
      }
      else {
        $transactions->union($query, 'ALL');
      }
    }

    // Keep all accounting arithmetic in SQL DECIMAL.
    $daily = $database->select($transactions, 't');
    $daily->fields('t', ['currency', 'transaction_date']);
    $daily->addExpression('SUM(t.income)', 'income');
    $daily->addExpression('SUM(t.expense)', 'expense');

    $daily->addExpression('SUM(SUM(t.income)) OVER (PARTITION BY t.currency)', 'total_income');
    $daily->addExpression('SUM(SUM(t.expense)) OVER (PARTITION BY t.currency)', 'total_expense');
    $daily->addExpression('SUM(SUM(t.income - t.expense)) OVER (PARTITION BY t.currency)', 'total_net');
    $daily->groupBy('t.currency')->groupBy('t.transaction_date')->orderBy('t.currency')->orderBy('t.transaction_date');
    $days = $daily->execute()->fetchAll();
    if (!$days) {
      $build['empty'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('No income or expenses match the selected filters.')];
      return $build;
    }

    $monthly = $database->select($transactions, 't');
    $monthly->addField('t', 'currency');
    $monthly->addExpression('SUBSTRING(t.transaction_date, 1, 7)', 'month');
    $monthly->addExpression('SUM(t.income)', 'income');
    $monthly->addExpression('SUM(t.expense)', 'expense');
    $monthly->groupBy('t.currency')->groupBy('month')->orderBy('t.currency')->orderBy('month');
    $months = [];
    foreach ($monthly->execute() as $month) {
      $months[$month->currency][] = $month;
    }
    $groups = [];
    foreach ($days as $day) {
      $groups[$day->currency][] = $day;
    }
    foreach ($groups as $currency => $records) {
      $label = $currency !== '' ? $currency : (string) $this->t('Unspecified currency');
      $section = [
        '#type' => 'container', '#attributes' => ['class' => ['balance-charts__currency']],
        'heading' => ['#type' => 'html_tag', '#tag' => 'h3', 'text' => ['#plain_text' => $label]],
        'metrics' => ['#type' => 'container', '#attributes' => ['class' => ['balance-charts__metrics']]],
      ];
      foreach (['total_income' => $this->t('Total income'), 'total_expense' => $this->t('Total expenses'), 'total_net' => $this->t('Net balance')] as $key => $title) {
        $value = (string) $records[0]->{$key};
        $classes = ['balance-charts__metric', 'balance-charts__metric--' . $key];
        if ($key === 'total_net' && str_starts_with($value, '-')) {
          $classes[] = 'is-negative';
        }
        $section['metrics'][$key] = [
          '#type' => 'container', '#attributes' => ['class' => $classes],
          'label' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $title],
          'value' => ['#type' => 'html_tag', '#tag' => 'strong', 'text' => ['#plain_text' => $this->formatAmount($value) . ' ' . $label]],
        ];
      }
      $section['panels'] = ['#type' => 'container', '#attributes' => ['class' => ['balance-charts__grid']]];
      $flow = ['kind' => 'flow', 'currency' => $label, 'labels' => [], 'datasets' => [
        ['label' => (string) $this->t('Income'), 'values' => [], 'formatted' => []],
        ['label' => (string) $this->t('Expenses'), 'values' => [], 'formatted' => []],
      ]];
      $flow_rows = [];
      foreach ($months[$currency] as $month) {
        $month_label = $month->month !== '' ? $month->month : (string) $this->t('Undated');
        $flow['labels'][] = $month_label;
        foreach (['income', 'expense'] as $index => $field) {
          $flow['datasets'][$index]['values'][] = (string) $month->{$field};
          $flow['datasets'][$index]['formatted'][] = $this->formatAmount((string) $month->{$field});
        }
        $flow_rows[] = [$month_label, $this->formatAmount((string) $month->income), $this->formatAmount((string) $month->expense)];
      }
      $section['panels']['flow'] = $this->panel(
        (string) $this->t('Monthly income vs expenses'),
        (string) $this->t('Compare incoming and outgoing amounts side by side for each month.'),
        $flow,
        [$this->t('Month'), $this->t('Income'), $this->t('Expenses')],
        $flow_rows,
      );
      $pie = ['kind' => 'pie', 'currency' => $label, 'labels' => [
        (string) $this->t('Income'),
        (string) $this->t('Expenses'),
      ], 'datasets' => [
        ['label' => (string) $this->t('Filtered transactions'), 'values' => [], 'formatted' => []],
      ]];
      $pie_rows = [];
      foreach (['total_income', 'total_expense'] as $index => $field) {
        $amount = (string) $records[0]->{$field};
        $pie['datasets'][0]['values'][] = $amount;
        $pie['datasets'][0]['formatted'][] = $this->formatAmount($amount);
        $pie_rows[] = [$pie['labels'][$index], $this->formatAmount($amount)];
      }
      $section['panels']['pie'] = $this->panel(
        (string) $this->t('Income vs expenses'),
        (string) $this->t('Share of total income and expenses matching the current list filters. Includes undated records. The difference is shown in Net balance above.'),
        $pie,
        [$this->t('Type'), $this->t('Amount')],
        $pie_rows,
      );
      if (!preg_match('/[1-9]/', implode('', $pie['datasets'][0]['values']))) {
        $section['panels']['pie']['empty'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Income and expenses are both zero for the selected records. There are no amounts to plot.')];
      }
      $build[] = $section;
    }
    return $build;
  }

  /**
   * Builds a chart with an accessible, exact-value data-table alternative.
   */
  private function panel(string $title, string $description, array $data, array $header, array $rows): array {
    return [
      '#type' => 'container', '#attributes' => ['class' => ['balance-charts__panel']],
      'title' => ['#type' => 'html_tag', '#tag' => 'h4', 'text' => ['#plain_text' => $title]],
      'description' => ['#type' => 'html_tag', '#tag' => 'p', 'text' => ['#plain_text' => $description]],
      'visual' => [
        '#type' => 'container', '#attributes' => ['class' => ['balance-charts__visual'], 'hidden' => TRUE],
        'canvas' => [
          '#type' => 'html_tag', '#tag' => 'canvas',
          '#attributes' => [
            'role' => 'img', 'aria-label' => $this->t('@title in @currency. Exact amounts are available in the accompanying accessible data table.', ['@title' => $title, '@currency' => $data['currency']]),
            'data-balance-chart' => Json::encode($data),
          ],
        ],
      ],
      'data' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['visually-hidden']],
        'table' => [
          '#type' => 'table', '#caption' => $data['currency'], '#header' => $header, '#rows' => $rows,
          '#empty' => $this->t('No income or expenses match the selected filters.'),
        ],
      ],
    ];
  }

  /**
   * Formats SQL decimals without converting accounting values to floats.
   */
  private function formatAmount(string $amount): string {
    $negative = str_starts_with($amount, '-');
    [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');
    return ($negative ? '-' : '') . preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
  }

}
