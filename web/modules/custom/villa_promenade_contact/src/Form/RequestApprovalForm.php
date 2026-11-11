<?php

namespace Drupal\villa_promenade_contact\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\views\Controller\ViewAjaxController;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Collects approval details before saving a customer request's Approved state.
 */
final class RequestApprovalForm extends FormBase {

  public function getFormId(): string {
    return 'villa_customer_request_approval';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'customer_request') {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('approval_node_id', $node->id());
    $node = $this->loadRequest($form_state);
    if (!$form_state->has('approval_context')) {
      $query = $this->getRequest()->query;
      $display = $query->get('view_display_id', 'page_1');
      if (!in_array($display, RequestStateActions::DISPLAYS, TRUE)) {
        throw new AccessDeniedHttpException();
      }
      $filters = array_diff_key($query->all('filters'), array_flip(ViewAjaxController::FILTERED_QUERY_PARAMETERS));
      $form_state->set('approval_context', [
        'display' => $display,
        'filters' => $filters,
        'page' => max(0, (int) $query->get('page', 0)),
        'args' => $query->all('view_args'),
        'dom_id' => preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $query->get('view_dom_id', '')),
      ]);
    }
    $context = $form_state->get('approval_context');
    $view = $this->originView($form_state);
    $submit_url = Url::fromRoute('villa_promenade_contact.approve_request', ['node' => $node->id()], [
      'query' => [
        'view_display_id' => $context['display'],
        'view_dom_id' => $context['dom_id'],
        'view_args' => $context['args'],
        'filters' => $context['filters'],
        'page' => $context['page'],
      ],
    ]);
    $form['#action'] = $submit_url->toString();
    $return_url = $view->getUrl()->setOption('query', $context['filters'] + ['page' => $context['page']]);
    $form['messages'] = ['#type' => 'status_messages'];
    $form['request'] = ['#type' => 'item', '#title' => $this->t('Customer request'), '#plain_text' => $node->label()];
    $form['real_date'] = [
      '#type' => 'date',
      '#title' => $node->getFieldDefinition('real_date')->getLabel(),
      '#default_value' => $node->get('real_date')->value,
      '#required' => TRUE,
    ];
    $form['approve_note'] = [
      '#type' => 'textarea',
      '#title' => $node->getFieldDefinition('approve_note')->getLabel(),
      '#default_value' => $node->get('approve_note')->value,
      '#rows' => 4,
    ];
    $form['request_state_view_dom_id'] = ['#type' => 'hidden', '#value' => $context['dom_id']];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and approve'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'url' => $submit_url,
        // Explicit AJAX URLs need the form marker and query options; core only
        // adds these automatically when it supplies the callback URL itself.
        'options' => ['query' => $submit_url->getOption('query') + [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]],
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $return_url,
      '#attributes' => ['class' => ['button', 'dialog-cancel']],
    ];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->loadRequest($form_state);
    $this->applyApproval($node, $form_state);
    foreach (['real_date', 'approve_note', 'request_status'] as $field) {
      foreach ($node->get($field)->validate() as $violation) {
        $form_state->setErrorByName($field, $violation->getMessage());
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->set('approval_saved', FALSE);
    $node = $this->loadRequest($form_state);
    $this->applyApproval($node, $form_state);
    try {
      // Store both approval fields and the state in a single entity save.
      $node->save();
      $form_state->set('approval_saved', TRUE);
      $this->messenger()->addStatus($this->t('Request #@id approved.', ['@id' => $node->id()]));
      $context = $form_state->get('approval_context');
      $form_state->setRedirectUrl($this->originView($form_state)->getUrl()->setOption('query', $context['filters'] + ['page' => $context['page']]));
    }
    catch (\Exception $exception) {
      $this->logger('villa_promenade_contact')->error('A customer request approval could not be saved.');
      $this->messenger()->addError($this->t('The approval could not be saved. Please try again.'));
      $form_state->setRebuild();
    }
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    if ($form_state->hasAnyErrors() || !$form_state->get('approval_saved')) {
      $response = new AjaxResponse();
      $response->addCommand(new OpenModalDialogCommand($this->t('Approve customer request'), $form, ['width' => 'min(640px, 94vw)']));
      return $response;
    }
    $response = RequestStateActions::refresh($this->originView($form_state), TRUE, [
      '#request_id' => $form_state->get('approval_node_id'),
      '#request_state' => 'approved',
    ]);
    $response->addCommand(new CloseModalDialogCommand(), TRUE);
    return $response;
  }

  /**
   * Reloads the current request and rechecks entity and field permissions.
   */
  private function loadRequest(FormStateInterface $form_state): NodeInterface {
    $id = $form_state->get('approval_node_id');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$id]);
    $node = $storage->load($id);
    if (!$this->currentUser()->hasPermission('manage villa customer requests') || !$node instanceof NodeInterface || $node->bundle() !== 'customer_request' || !$node->access('update')) {
      throw new AccessDeniedHttpException();
    }
    foreach (['real_date', 'approve_note', 'request_status'] as $field) {
      if (!$node->hasField($field) || !$node->get($field)->access('edit') || !$node->get($field)->access('view')) {
        throw new AccessDeniedHttpException();
      }
    }
    return $node;
  }

  private function applyApproval(NodeInterface $node, FormStateInterface $form_state): void {
    $date = (string) $form_state->getValue('real_date');
    $node->set('real_date', $date === '' ? [] : ['value' => $date]);
    $node->set('approve_note', (string) $form_state->getValue('approve_note'));
    $node->set('request_status', 'approved');
  }

  /**
   * Restores the original listing, including filters, pager and DOM identity.
   */
  private function originView(FormStateInterface $form_state): ViewExecutable {
    $context = $form_state->get('approval_context');
    $view = Views::getView('villa_customer_requests');
    if (!$view || !$view->access($context['display'])) {
      throw new AccessDeniedHttpException();
    }
    $view->setDisplay($context['display']);
    $view->setArguments($context['args']);
    $view->setExposedInput($context['filters']);
    $view->setCurrentPage($context['page']);
    if ($context['dom_id'] !== '') {
      $view->dom_id = $context['dom_id'];
    }
    return $view;
  }

}
