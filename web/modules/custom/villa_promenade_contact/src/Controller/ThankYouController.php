<?php

namespace Drupal\villa_promenade_contact\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Displays the confirmation after a saved event enquiry.
 */
final class ThankYouController extends ControllerBase {

  /**
   * Builds the confirmation without exposing any submitted personal data.
   */
  public function build(): array {
    return [
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#attributes' => ['class' => ['villa-thank-you__title']],
        '#value' => $this->t('Köszönjük a megkeresést, hamarosan jelentkezünk!'),
      ],
      'home' => [
        '#type' => 'link',
        '#title' => $this->t('Vissza a főoldalra'),
        '#url' => Url::fromRoute('<front>'),
        '#attributes' => ['class' => ['villa-thank-you__home']],
      ],
    ];
  }

}
