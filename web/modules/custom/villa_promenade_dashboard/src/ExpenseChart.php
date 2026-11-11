<?php

namespace Drupal\villa_promenade_dashboard;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;

/**
 * Builds category pie charts from filtered income and expense records.
 */
final class ExpenseChart {

  use StringTranslationTrait;

  /**
   * Builds separate currency charts without changing the financial listing.
   */
  public function build(ViewExecutable $view): array {
    $type = match ($view->id()) {
      'expenses' => 'expense',
      'income' => 'income',
      default => throw new \InvalidArgumentException('Unsupported financial chart View.'),
    };
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['expense-charts']],
      '#attached' => ['library' => ['villa_promenade_dashboard/expense-chart']],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user.permissions', 'url.query_args', 'languages:language_content'],
        'tags' => ['node_list:' . $type, 'taxonomy_term_list:' . $type . '_category'],
      ],
    ];
    if (!\Drupal::currentUser()->hasPermission('view villa financial reports')) {
      $build['#access'] = FALSE;
      return $build;
    }
    $build['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $type === 'income' ? $this->t('Income by category') : $this->t('Expenses by category')];
    $build['description'] = [
      '#type' => 'html_tag', '#tag' => 'p',
      '#value' => $this->t('Based on the selected date and user filters. Each currency is shown separately; hover over a slice for details.'),
    ];

    // Reuse the actual filtered query instead of reinterpreting raw URL input.
    $filtered = clone $view->build_info['query'];
    $filtered->range(NULL, NULL);
    $order = &$filtered->getOrderBy();
    $order = [];
    unset($order);
    $database = \Drupal::database();
    $node_ids = $database->select($filtered, 'filtered_records')->fields('filtered_records', ['nid']);
    $query = $database->select('node_field_data', 'n');
    $query->innerJoin('node__field_' . $type . '_amount', 'a', 'a.entity_id = n.nid AND a.langcode = n.langcode AND a.delta = 0 AND a.deleted = 0');
    $query->leftJoin('node__field_' . $type . '_currency', 'c', 'c.entity_id = n.nid AND c.langcode = n.langcode AND c.delta = 0 AND c.deleted = 0');
    $query->leftJoin('node__field_' . $type . '_category', 'cat', 'cat.entity_id = n.nid AND cat.langcode = n.langcode AND cat.delta = 0 AND cat.deleted = 0');
    $query->condition('n.type', $type)->condition('n.default_langcode', 1)->condition('n.nid', $node_ids, 'IN');
    $query->addExpression("COALESCE(c.field_{$type}_currency_value, '')", 'currency');
    $query->addExpression('COALESCE(cat.field_' . $type . '_category_target_id, 0)', 'category_id');
    $query->addExpression('SUM(a.field_' . $type . '_amount_value)', 'amount');
    $query->groupBy('currency')->groupBy('category_id')->orderBy('currency')->orderBy('amount', 'DESC')->orderBy('category_id');
    $totals = $query->execute()->fetchAll();
    if (!$totals) {
      $build['empty'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $type === 'income' ? $this->t('No income matches the selected filters.') : $this->t('No expenses match the selected filters.')];
      return $build;
    }
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple(array_unique(array_column($totals, 'category_id')));
    $groups = [];
    foreach ($totals as $total) {
      $label = $this->t('Uncategorized');
      if (isset($terms[$total->category_id])) {
        $term = \Drupal::service('entity.repository')->getTranslationFromContext($terms[$total->category_id]);
        $label = $term->access('view') ? $term->label() : $this->t('Restricted category');
      }
      $groups[$total->currency][] = ['label' => (string) $label, 'amount' => (string) $total->amount];
    }
    $build['charts'] = ['#type' => 'container', '#attributes' => ['class' => ['expense-charts__grid']]];
    foreach ($groups as $currency => $categories) {
      $currency_label = $currency !== '' ? $currency : (string) $this->t('Unspecified currency');
      $card = [
        '#type' => 'container', '#attributes' => ['class' => ['expense-chart']],
        'heading' => ['#type' => 'html_tag', '#tag' => 'h3', 'text' => ['#plain_text' => $currency_label]],
      ];
      $labels = [];
      $amounts = [];
      $formatted = [];
      $rows = [];
      foreach ($categories as $category) {
        [$whole, $fraction] = array_pad(explode('.', $category['amount'], 2), 2, '');
        $amount = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
        $labels[] = $category['label'];
        $amounts[] = $category['amount'];
        $formatted[] = $amount;
        $rows[] = [
          ['data' => ['#plain_text' => $category['label']]],
          ['data' => ['#plain_text' => $amount . ' ' . $currency_label]],
        ];
      }
      // Keep the exact amounts visible without JavaScript or canvas support.
      $card['visual'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['expense-chart__visual'], 'hidden' => TRUE],
        'canvas' => [
          '#type' => 'html_tag', '#tag' => 'canvas',
          '#attributes' => [
            'role' => 'img',
            'aria-label' => $type === 'income'
              ? $this->t('Income by category in @currency. Exact amounts are listed in the table below.', ['@currency' => $currency_label])
              : $this->t('Expenses by category in @currency. Exact amounts are listed in the table below.', ['@currency' => $currency_label]),
            'data-expense-pie' => Json::encode(['type' => $type, 'labels' => $labels, 'amounts' => $amounts, 'formatted' => $formatted, 'currency' => $currency_label]),
          ],
        ],
      ];
      $card['table'] = [
        '#type' => 'table',
        '#header' => [$this->t('Category'), $this->t('Amount')],
        '#rows' => $rows,
        '#attributes' => ['class' => ['expense-chart__legend']],
      ];
      $build['charts'][] = $card;
    }
    return $build;
  }

}
