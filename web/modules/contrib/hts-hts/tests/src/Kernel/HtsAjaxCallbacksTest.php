<?php

namespace Drupal\Tests\hts\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hts\HtsConstants;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves HTS AJAX callbacks never clear unrelated messenger messages.
 */
#[Group('hts')]
#[RunTestsInSeparateProcesses]
class HtsAjaxCallbacksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'taxonomy',
    'hts',
  ];

  /**
   * Tests the plain-select callback.
   */
  public function testPlainAjaxPreservesMessages(): void {
    $this->assertCallbackPreservesMessages('selectCallback');
  }

  /**
   * Tests the Select2 callback and its plain fallback.
   */
  public function testSelect2AjaxPreservesMessages(): void {
    $this->assertCallbackPreservesMessages('select2Callback');
  }

  /**
   * Invokes one callback while a status message is queued.
   */
  private function assertCallbackPreservesMessages(string $callback): void {
    $messenger = $this->container->get('messenger');
    $messenger->addStatus('Keep this message.');
    $trigger = [
      '#array_parents' => ['levels', 0],
      '#hts_level' => 0,
      '#hts_delta' => 0,
      '#hts_depth' => 1,
      '#hts_handler_settings' => [],
      '#value' => HtsConstants::NONE_VALUE,
    ];
    $form = ['levels' => [$trigger]];
    $form_state = new FormState();
    $form_state->setTriggeringElement($trigger);

    $this->container->get('hts.ajax_callbacks')->{$callback}(
      $form,
      $form_state,
      ['hierarchy_depth' => 1],
      'field_tags',
    );

    $this->assertSame(
      ['Keep this message.'],
      array_map('strval', $messenger->messagesByType(MessengerInterface::TYPE_STATUS)),
    );
  }

}
