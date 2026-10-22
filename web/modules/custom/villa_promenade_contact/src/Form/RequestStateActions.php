<?php

namespace Drupal\villa_promenade_contact\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Controller\ViewAjaxController;
use Drupal\views\Views;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Adds per-request state buttons to the existing, CSRF-protected Views form.
 */
final class RequestStateActions {

  /**
   * Page displays sharing the request tiles and their state controls.
   */
  public const DISPLAYS = ['page_1', 'page_cancelled', 'page_archive'];

  /**
   * States available directly on a request tile.
   */
  public const STATES = [
    'cancelled' => 'Cancelled',
    'archive' => 'Archive',
    'new' => 'New',
  ];

  /**
   * Adds controls through Views substitutions, avoiding nested HTML forms.
   */
  public static function build(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->getBuildInfo()['args'][0] ?? NULL;
    if (!$view || $view->id() !== 'villa_customer_requests' || !in_array($view->current_display, self::DISPLAYS, TRUE)) {
      return;
    }
    $query = array_diff_key($view->getExposedInput(), array_flip(ViewAjaxController::FILTERED_QUERY_PARAMETERS));
    $query['page'] = $view->getCurrentPage();
    $query[FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
    foreach ($view->result as $index => $row) {
      $request = $row->_entity;
      $field = $request->get('request_status');
      $access = $request->access('update', NULL, TRUE)
        ->andIf($field->access('edit', NULL, TRUE))
        ->andIf($field->access('view', NULL, TRUE));
      $current = (string) $field->value;
      $labels = $field->getFieldDefinition()->getSetting('allowed_values');
      $form['request_state'][$index] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['request-tile__state-actions']],
        '#access' => $access,
        'current' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => isset(self::STATES[$current]) ? ['visually-hidden'] : ['request-tile__state-current']],
          'text' => ['#plain_text' => t('State: @state', ['@state' => $labels[$current] ?? $current])],
        ],
      ];
      CacheableMetadata::createFromObject($request)->applyTo($form['request_state'][$index]);
      foreach (self::STATES as $state => $label) {
        $form['request_state'][$index][$state] = [
          '#type' => 'submit',
          '#value' => t($label),
          '#name' => 'request-state-' . $request->id() . '-' . $state,
          '#request_id' => $request->id(),
          '#request_state' => $state,
          '#disabled' => $current === $state,
          '#attributes' => [
            'class' => array_filter(['button--small', 'request-tile__state-button', 'request-tile__state-button--' . $state, $current === $state ? 'is-current' : NULL]),
            'aria-pressed' => $current === $state ? 'true' : 'false',
            'aria-label' => $current === $state
              ? t('Request #@id current state: @state', ['@id' => $request->id(), '@state' => t($label)])
              : t('Set request #@id to @state', ['@id' => $request->id(), '@state' => t($label)]),
          ],
          '#limit_validation_errors' => [],
          '#submit' => [[self::class, 'submit']],
          '#ajax' => [
            'callback' => [self::class, 'ajax'],
            // The current route can be /views/ajax after filtering or paging.
            // Submit the state form to its page, not that Views-only endpoint.
            'url' => $view->getUrl(),
            'options' => ['query' => $query],
            'submit' => ['request_state_view_dom_id' => $view->dom_id],
            'progress' => ['type' => 'throbber'],
          ],
        ];
      }
      $form['#substitutions']['#value'][] = [
        'placeholder' => '<!--form-item-request_state--' . $index . '-->',
        'field_name' => 'request_state',
        'row_id' => $index,
      ];
    }
  }

  /**
   * Saves only the selected request's state after rechecking current access.
   */
  public static function submit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('request_state_updated', FALSE);
    $trigger = $form_state->getTriggeringElement();
    $state = $trigger['#request_state'] ?? '';
    if (!isset(self::STATES[$state])) {
      throw new AccessDeniedHttpException();
    }
    $storage = \Drupal::entityTypeManager()->getStorage('villa_customer_request');
    $id = $trigger['#request_id'] ?? NULL;
    if (!$id) {
      throw new AccessDeniedHttpException();
    }
    $storage->resetCache([$id]);
    $request = $storage->load($id);
    if (!$request || !$request->access('update') || !$request->get('request_status')->access('edit')) {
      throw new AccessDeniedHttpException();
    }
    $request->set('request_status', $state);
    if ($request->get('request_status')->validate()->count()) {
      throw new \UnexpectedValueException('Invalid customer request state.');
    }
    try {
      $request->save();
      \Drupal::messenger()->addStatus(t('Request #@id set to @state.', ['@id' => $id, '@state' => t(self::STATES[$state])]));
      $form_state->set('request_state_updated', TRUE);
    }
    catch (\Exception $exception) {
      \Drupal::logger('villa_promenade_contact')->error('A customer request state could not be saved.');
      \Drupal::messenger()->addError(t('The request state could not be saved. Please try again.'));
    }
    // Keep a normal POST/redirect fallback when JavaScript is unavailable.
    $view = $form_state->getBuildInfo()['args'][0];
    $query = array_diff_key($view->getExposedInput(), array_flip(ViewAjaxController::FILTERED_QUERY_PARAMETERS));
    $query['page'] = $view->getCurrentPage();
    $form_state->setRedirectUrl($view->getUrl()->setOption('query', $query));
  }

  /**
   * Refreshes tiles, filters and paging without discarding the current filters.
   */
  public static function ajax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $original = $form_state->getBuildInfo()['args'][0];
    // Exposed input also contains AJAX transport parameters on real requests.
    // Do not let ajax_form turn fresh GET forms into broken POST submissions.
    $input = array_diff_key($original->getExposedInput(), array_flip(ViewAjaxController::FILTERED_QUERY_PARAMETERS));
    $view = Views::getView($original->id());
    $view->setDisplay($original->current_display);
    $view->setArguments($original->args);
    $view->setExposedInput($input);
    $view->setCurrentPage($original->getCurrentPage());
    // An uncached form rebuild can give the View a new ID on POST. Keep the
    // ID of the View actually displayed in the browser, as core Views does.
    $dom_id = \Drupal::request()->request->get('request_state_view_dom_id');
    $view->dom_id = $dom_id ? preg_replace('/[^a-zA-Z0-9_-]+/', '-', $dom_id) : $original->dom_id;
    $selector = '.js-view-dom-id-' . $view->dom_id;
    // Render fresh forms in a GET context, never replay the current state POST.
    $request = clone \Drupal::request();
    $request->setMethod('GET');
    $request->request->replace([]);
    $request->query->replace($input + ['page' => $original->getCurrentPage()]);
    \Drupal::requestStack()->push($request);
    try {
      $response = new AjaxResponse();
      if ($form_state->get('request_state_updated')) {
        $trigger = $form_state->getTriggeringElement();
        // Remove the moved tile only after saving, even if its View DOM ID
        // changed while Drupal rebuilt the submitted form.
        $tile = '.view-villa-customer-requests .request-tile:has([name="request-state-' . (int) $trigger['#request_id'] . '-' . $trigger['#request_state'] . '"])';
        $response->addCommand(new RemoveCommand($tile));
      }
      $response->addCommand(new ReplaceCommand($selector, $view->buildRenderable($original->current_display)));
      $response->addCommand(new PrependCommand($selector, ['#type' => 'status_messages']));
      if ($form_state->get('request_state_updated')) {
        $response->addCommand(new AnnounceCommand(t('Request state updated.')));
      }
      return $response;
    }
    finally {
      \Drupal::requestStack()->pop();
    }
  }

}
