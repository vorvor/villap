<?php

namespace Drupal\villa_promenade_contact;

use Drupal\views\EntityViewsData;

/**
 * Exposes dropdown filters for the request entity's base list fields.
 */
final class CustomerRequestViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    foreach (['event', 'request_status'] as $field) {
      $data['villa_customer_request'][$field]['filter']['id'] = 'list_field';
      $data['villa_customer_request'][$field]['filter']['field_name'] = $field;
      $data['villa_customer_request'][$field]['filter']['entity_type'] = 'villa_customer_request';
    }
    return $data;
  }

}
