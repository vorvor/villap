<?php

namespace Drupal\villa_promenade_contact\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\villa_promenade_contact\EventQuestions;
use Drupal\villa_promenade_contact\RequestNotifier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a contact form with conditional event details and private storage.
 */
final class EventContactForm extends FormBase {

  /**
   * Constructs the event contact form.
   */
  public function __construct(
    private readonly TimeInterface $time,
    private readonly PrivateKey $privateKey,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestNotifier $requestNotifier,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('private_key'),
      $container->get('entity_type.manager'),
      $container->get('villa_promenade_contact.request_notifier'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'villa_promenade_event_contact_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'villa-promenade-contact-form';
    $form['#attached']['library'][] = 'villa_promenade_contact/form';
    $form['#cache']['max-age'] = 0;

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#maxlength' => 100,
      '#attributes' => ['autocomplete' => 'name'],
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'email'],
    ];
    $form['event'] = [
      '#type' => 'select',
      '#title' => $this->t('Event'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select an event -'),
      '#options' => EventQuestions::eventOptions(),
    ];

    foreach ($this->eventFields() as $event => $fields) {
      $form[$event] = [
        '#type' => 'fieldset',
        '#title' => EventQuestions::eventOptions()[$event],
        '#title_display' => 'invisible',
        '#attributes' => ['class' => ['villa-contact-event-details']],
        '#tree' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="event"]' => ['value' => $event],
          ],
        ],
      ] + $fields;
    }

    $form['website'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leave this field empty'),
      '#maxlength' => 255,
      '#wrapper_attributes' => [
        'class' => ['villa-contact-honeypot'],
        'aria-hidden' => 'true',
      ],
      '#attributes' => [
        'tabindex' => '-1',
        'autocomplete' => 'off',
      ],
    ];
    $timestamp = (string) $this->time->getCurrentTime();
    $form['contact_started'] = [
      '#type' => 'hidden',
      '#default_value' => $timestamp . ':' . $this->signTimestamp($timestamp),
    ];
    $form['privacy_notice'] = [
      '#type' => 'item',
      '#markup' => $this->t('Your name, email and selected event details will be saved privately so our team can manage your request.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send enquiry'),
    ];

    return $form;
  }

  /**
   * Builds reference questions without requiring fields in inactive sections.
   */
  private function eventFields(): array {
    $events = EventQuestions::definitions();
    foreach ($events as $event => &$fields) {
      foreach (array_keys($fields) as $key) {
        $definition = &$fields[$key];
        if (!empty($definition['#has_other'])) {
          $selector = $definition['#type'] === 'checkboxes'
            ? ':input[name="' . $event . '[' . $key . '][other]"]'
            : ':input[name="' . $event . '[' . $key . ']"]';
          $condition = $definition['#type'] === 'checkboxes' ? ['checked' => TRUE] : ['value' => 'other'];
          $fields[$key . '_other'] = [
            '#type' => 'textfield',
            '#title' => $this->t('@question – Egyéb', ['@question' => $definition['#title']]),
            '#maxlength' => 500,
            '#states' => ['visible' => [$selector => $condition]],
            '#other_for' => $key,
          ];
        }
        unset($definition);
      }
      // Keep each Other input immediately after its corresponding question.
      $ordered = [];
      foreach (EventQuestions::definitions()[$event] as $key => $definition) {
        $ordered[$key] = $fields[$key];
        if (isset($fields[$key . '_other'])) {
          $ordered[$key . '_other'] = $fields[$key . '_other'];
        }
      }
      $fields = $ordered;
    }
    unset($fields);
    return $events;
  }

  /**
   * Whether a question's Other option was selected.
   */
  private function hasOther(mixed $value): bool {
    return is_array($value) ? in_array('other', $value, TRUE) : $value === 'other';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $event = $form_state->getValue('event');
    foreach ($this->eventFields()[$event] ?? [] as $key => $definition) {
      $value = $form_state->getValue([$event, $key], '');
      $empty = is_array($value) ? !array_filter($value) : trim((string) $value) === '';
      if (!empty($definition['#event_required']) && $empty) {
        $form_state->setError($form[$event][$key], $this->t('@question: kötelező válaszolni.', ['@question' => $definition['#title']]));
      }
      if (isset($definition['#other_for']) && $this->hasOther($form_state->getValue([$event, $definition['#other_for']])) && $empty) {
        $form_state->setError($form[$event][$key], $this->t('Kérjük, részletezzétek az egyéb választ.'));
      }
    }
    $arrival_time = $form_state->getValue([$event, 'arrival_time'], '');
    if ($event === 'event1' && $arrival_time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $arrival_time)) {
      $form_state->setError($form[$event]['arrival_time'], $this->t('Kérjük, érvényes időpontot adjatok meg (ÓÓ:PP).'));
    }
    if ((string) $form_state->getValue('website', '') !== '') {
      $form_state->setErrorByName('website', $this->t('Your submission could not be accepted. Please reload the form and try again.'));
    }

    $token = $form_state->getValue('contact_started', '');
    $parts = is_string($token) ? explode(':', $token, 2) : [];
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !hash_equals($this->signTimestamp($parts[0]), $parts[1])) {
      $form_state->setErrorByName('contact_started', $this->t('The form has expired. Please reload the page and try again.'));
      return;
    }

    $elapsed = $this->time->getCurrentTime() - (int) $parts[0];
    if ($elapsed < 3) {
      $form_state->setErrorByName('contact_started', $this->t('Please wait a few seconds before submitting the form.'));
    }
    elseif ($elapsed > 7200) {
      $form_state->setErrorByName('contact_started', $this->t('The form has expired. Please reload the page and try again.'));
    }
  }

  /**
   * Signs the form's timing token so clients cannot backdate it.
   */
  private function signTimestamp(string $timestamp): string {
    return Crypt::hmacBase64($this->getFormId() . ':' . $timestamp, $this->privateKey->get() . Settings::getHashSalt());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event = $form_state->getValue('event');
    $details = [];
    // Whitelist only fields from the selected event; ignore hidden sections.
    foreach ($this->eventFields()[$event] as $key => $definition) {
      if (isset($definition['#other_for']) && !$this->hasOther($form_state->getValue([$event, $definition['#other_for']]))) {
        continue;
      }
      $value = $form_state->getValue([$event, $key], '');
      if (is_array($value) && $definition['#type'] === 'checkboxes') {
        $selected = array_intersect_key($definition['#options'], array_filter($value));
        if ($selected) {
          $details[] = $definition['#title'] . ': ' . implode(', ', $selected);
        }
        continue;
      }
      if (is_scalar($value) && (string) $value !== '') {
        $label = (string) $definition['#title'];
        $display_value = $definition['#options'][$value] ?? $value;
        $details[] = $label . ': ' . (string) $display_value;
      }
    }
    try {
      $request = $this->entityTypeManager->getStorage('villa_customer_request')->create([
        'name' => $form_state->getValue('name'),
        'email' => $form_state->getValue('email'),
        'event' => $event,
        'details' => implode("\n", $details),
        'request_status' => 'new',
        'admin_notes' => '',
        'is_demo' => FALSE,
      ]);
      if ($request->validate()->count()) {
        throw new \UnexpectedValueException('Invalid customer request.');
      }
      $request->save();
    }
    catch (\Exception $exception) {
      // Do not log submitted personal data or database exception messages.
      $this->logger('villa_promenade_contact')->error('A customer request could not be saved.');
      $this->messenger()->addError($this->t('Your request could not be saved. Please try again.'));
      $form_state->setRebuild();
      return;
    }
    try {
      $this->requestNotifier->notify($request);
    }
    catch (\Exception $exception) {
      $this->logger('villa_promenade_contact')->error('Email notification failed for saved request @id. The request remains available in administration.', ['@id' => $request->id()]);
    }
    $form_state->setRedirect('villa_promenade_contact.thank_you');
  }

}
