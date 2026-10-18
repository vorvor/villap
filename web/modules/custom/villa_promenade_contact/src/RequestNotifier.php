<?php

namespace Drupal\villa_promenade_contact;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\villa_promenade_contact\Entity\CustomerRequest;

/**
 * Notifies configured staff after a public request has been saved.
 */
final class RequestNotifier {

  /**
   * Constructs the request notifier.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MailManagerInterface $mailManager,
    private readonly EmailValidatorInterface $emailValidator,
    private readonly LanguageManagerInterface $languageManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Sends separate notifications without compromising persisted requests.
   *
   * @return array
   *   Numbers of notifications accepted by the mail backend and failed.
   */
  public function notify(CustomerRequest $request): array {
    $result = ['sent' => 0, 'failed' => 0];
    $config = $this->configFactory->get('villa_promenade_contact.settings');
    if (!$config->get('notifications_enabled') || $request->isNew() || $request->get('is_demo')->value) {
      return $result;
    }
    $body = [
      'A new Villa Promenade customer request has been received.',
      '',
      'Request ID: ' . $request->id(),
      'Customer name: ' . $request->get('name')->value,
      'Customer email: ' . $request->get('email')->value,
      'Event: ' . (EventQuestions::eventOptions()[$request->get('event')->value] ?? $request->get('event')->value),
      'Submitted (UTC): ' . gmdate('Y-m-d H:i:s', (int) $request->get('created')->value),
      '',
      'Selected event details:',
      $request->get('details')->value ?: '(No additional details provided.)',
      '',
      'Manage this request (staff login required):',
      $request->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $params = [
      'subject' => 'Villa Promenade request #' . $request->id() . ' - ' . (EventQuestions::eventOptions()[$request->get('event')->value] ?? $request->get('event')->value),
      'body' => implode("\n", $body),
    ];
    $reply = (string) $request->get('email')->value;
    if (!$this->emailValidator->isValid($reply) || preg_match('/[\r\n]/', $reply)) {
      $reply = NULL;
    }
    $seen = [];
    foreach ($config->get('recipients') ?? [] as $recipient) {
      if (!is_string($recipient) || !$this->emailValidator->isValid($recipient) || preg_match('/[\r\n]/', $recipient)) {
        $result['failed']++;
        continue;
      }
      if (isset($seen[strtolower($recipient)])) {
        continue;
      }
      $seen[strtolower($recipient)] = TRUE;
      try {
        $message = $this->mailManager->mail(
          'villa_promenade_contact',
          'customer_request',
          $recipient,
          $this->languageManager->getDefaultLanguage()->getId(),
          $params,
          $reply,
          TRUE,
        );
        $result[!empty($message['result']) ? 'sent' : 'failed']++;
      }
      catch (\Exception $exception) {
        $result['failed']++;
      }
    }
    if (!$seen || $result['failed']) {
      // Never include customer data, recipient addresses or exception text.
      $this->loggerFactory->get('villa_promenade_contact')->error('Email notification failed or recipients are missing for saved request @id. The request remains available in administration.', ['@id' => $request->id()]);
    }
    return $result;
  }

}
