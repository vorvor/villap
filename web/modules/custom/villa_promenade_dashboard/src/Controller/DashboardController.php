<?php

namespace Drupal\villa_promenade_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\views\Views;

/**
 * Reuses the financial ledger as the administration landing page.
 */
final class DashboardController extends ControllerBase {

  /**
   * Builds the dashboard with native node forms and the existing Balance View.
   */
  public function build(): array {
    $view = Views::getView('balance');
    if (!$view || !$view->access('page_1')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['finance-dashboard-intro']],
      'description' => ['#markup' => $this->t('Your income, expenses and balance in one place. Add a record using the buttons above, or use the filters to explore your transactions.')],
    ];

    // Keep filters and pager links on the dashboard, without copying the View.
    $view->override_url = Url::fromRoute('villa_promenade_dashboard.page');
    $build['ledger'] = $view->buildRenderable('page_1');
    if ($this->currentUser()->hasPermission('manage villa customer requests')) {
      $tabs = QuickTabsInstance::load('dashboard_customer_requests');
      if ($tabs) {
        $build['customer_requests'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['dashboard-customer-requests']],
          '#attached' => ['library' => ['villa_promenade_dashboard/request-tabs']],
          '#weight' => 20,
          'heading' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Customer requests')],
          'tabs' => $tabs->getRenderArray(),
        ];
      }
    }
    $build['#attached']['library'][] = 'villa_promenade_dashboard/dashboard';
    $build['#cache']['contexts'] = ['url.query_args', 'user.permissions'];
    $build['#cache']['tags'] = ['config:views.view.balance', 'config:views.view.villa_customer_requests', 'config:views.view.villa_customer_requests_calendar', 'config:quicktabs.quicktabs_instance.dashboard_customer_requests'];
    return $build;
  }

}
