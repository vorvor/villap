<?php

namespace Drupal\villa_promenade_contact\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Lets request managers update customer details, status and internal notes.
 */
final class CustomerRequestForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = $this->entity->save();
    $this->messenger()->addStatus($this->t('Customer request saved.'));
    $form_state->setRedirect('view.villa_customer_requests.page_1');
    return $result;
  }

}
