<?php

namespace Drupal\villa_promenade_contact\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures staff email notifications for new customer requests.
 */
final class ContactSettingsForm extends ConfigFormBase {

  /**
   * Constructs the notification settings form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly EmailValidatorInterface $emailValidator,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('email.validator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'villa_promenade_contact_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['villa_promenade_contact.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('villa_promenade_contact.settings');
    $form['notifications_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email staff when a new request is submitted'),
      '#default_value' => $config->get('notifications_enabled'),
    ];
    $form['recipients'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notification email addresses'),
      '#description' => $this->t('Enter one email address per line, or separate addresses with commas or semicolons. Each recipient receives a separate email with the customer name, email and selected event details. Use only trusted staff addresses. Requests are still saved if notifications are disabled or email delivery fails.'),
      '#default_value' => implode("\n", $config->get('recipients') ?? []),
      '#rows' => 5,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $addresses = preg_split('/[\s,;]+/', trim((string) $form_state->getValue('recipients')), -1, PREG_SPLIT_NO_EMPTY);
    $recipients = [];
    foreach ($addresses as $address) {
      if (!$this->emailValidator->isValid($address)) {
        $form_state->setErrorByName('recipients', $this->t('Enter valid email addresses without display names. Invalid address: @address', ['@address' => $address]));
      }
      else {
        $recipients[strtolower($address)] ??= $address;
      }
    }
    if ($form_state->getValue('notifications_enabled') && !$recipients) {
      $form_state->setErrorByName('recipients', $this->t('At least one email address is required when notifications are enabled.'));
    }
    $form_state->set('validated_recipients', array_values($recipients));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('villa_promenade_contact.settings')
      ->set('notifications_enabled', (bool) $form_state->getValue('notifications_enabled'))
      ->set('recipients', $form_state->get('validated_recipients'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
