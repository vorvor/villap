<?php

namespace Drupal\villa_promenade_contact\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Deletes a private request and returns to the Views administration page.
 */
final class CustomerRequestDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('view.villa_customer_requests.page_1');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return $this->getCancelUrl();
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage() {
    $this->logger('villa_promenade_contact')->info('Customer request @id deleted.', ['@id' => $this->entity->id()]);
  }

}
