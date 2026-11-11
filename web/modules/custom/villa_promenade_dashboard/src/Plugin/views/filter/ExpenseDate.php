<?php

namespace Drupal\villa_promenade_dashboard\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\Date;

/**
 * Filters the standalone expense date with native calendar inputs.
 */
#[ViewsFilter('villa_expense_date')]
class ExpenseDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    if ($form_state->get('exposed') && isset($form['value']['#type'])) {
      $form['value']['#type'] = 'date';
      unset($form['value']['#size']);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    $value = (string) $this->value['value'];
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $value) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    // Date-only values are stored as YYYY-MM-DD, without time or timezone.
    $this->query->addWhere($this->options['group'], $field, $value, $this->operator);
  }

}
