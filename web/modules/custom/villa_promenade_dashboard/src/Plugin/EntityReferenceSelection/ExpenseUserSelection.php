<?php

namespace Drupal\villa_promenade_dashboard\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Selects expense users without offering the site's admin account.
 */
#[EntityReferenceSelection(
  id: 'villa_expense_user',
  label: new TranslatableMarkup('Expense users (excluding admin)'),
  entity_types: ['user'],
  group: 'villa_expense_user',
  weight: 0,
)]
class ExpenseUserSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    return parent::buildEntityQuery($match, $match_operator)->condition('uid', 1, '<>');
  }

  /**
   * Defaults to the current user unless that account is excluded.
   */
  public static function defaultValue(): array {
    $uid = \Drupal::currentUser()->id();
    return $uid && (int) $uid !== 1 ? [['target_id' => $uid]] : [];
  }

}
