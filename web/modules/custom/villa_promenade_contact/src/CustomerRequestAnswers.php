<?php

namespace Drupal\villa_promenade_contact;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Maps each public event question to a native, configurable node field.
 */
final class CustomerRequestAnswers {

  /**
   * Shares question labels, options and ordering with the public form.
   */
  public static function definitions(): array {
    $fields = [];
    foreach (EventQuestions::definitions() as $event => $questions) {
      foreach ($questions as $key => $question) {
        $name = self::fieldName($key);
        if (!isset($fields[$name])) {
          $fields[$name] = $question + ['key' => $key, 'events' => [], 'questions' => []];
          if (isset($question['#options'])) {
            $fields[$name]['#options'] = [];
          }
        }
        $fields[$name]['events'][] = $event;
        $fields[$name]['questions'][$event] = $question;
        foreach ($question['#options'] ?? [] as $option => $label) {
          // Generic option_1 identifiers mean different things between events.
          // Use the label to share identical choices without conflating values.
          $stored = self::optionKey($label, $option === 'other');
          $fields[$name]['#options'][$stored] = trim($label);
        }
        if (!empty($question['#has_other'])) {
          $other = self::fieldName($key . '_other');
          $fields[$other] ??= [
            '#type' => 'textfield',
            '#title' => $question['#title'] . ' – Egyéb',
            '#maxlength' => 500,
            'events' => [],
            'questions' => [],
            'key' => $key . '_other',
            'other_for' => $name,
          ];
          $fields[$other]['events'][] = $event;
          $fields[$other]['questions'][$event] = ['#title' => $question['#title'] . ' – Egyéb'];
        }
      }
    }
    return $fields;
  }

  /**
   * Returns one storage field for a question shared by different event types.
   */
  public static function fieldName(string $key): string {
    return 'cr_' . $key;
  }

  /**
   * Uses a stable, label-based key across event-specific choice lists.
   */
  public static function optionKey(string $label, bool $other = FALSE): string {
    return $other ? 'other' : 'choice_' . substr(hash('sha256', trim(str_replace(['–', '—'], '-', $label))), 0, 20);
  }

  /**
   * Removes the retired empty event-specific fields after content deletion.
   */
  public static function removeEventFields(): void {
    if (\Drupal::entityTypeManager()->getStorage('node')->getQuery()->accessCheck(FALSE)->condition('type', 'customer_request')->count()->execute()) {
      throw new \RuntimeException('Delete customer request content before replacing its fields.');
    }
    foreach (FieldConfig::loadMultiple() as $field) {
      if ($field->getTargetEntityTypeId() === 'node' && $field->getTargetBundle() === 'customer_request' && preg_match('/^cr_(event1|event2|other)_/', $field->getName())) {
        // Drupal also removes storage when no other bundle uses the field.
        $field->delete();
      }
    }
  }

  /**
   * Creates only missing fields and adds their native widgets and formatters.
   */
  public static function configure(): void {
    $manager = \Drupal::entityTypeManager();
    $form = $manager->getStorage('entity_form_display')->load('node.customer_request.default');
    $view = $manager->getStorage('entity_view_display')->load('node.customer_request.default');
    $weight = 10;
    foreach (self::definitions() as $name => $question) {
      $type = match ($question['#type']) {
        'checkboxes', 'radios' => 'list_string',
        'number' => 'integer',
        'date' => 'datetime',
        'textarea' => 'string_long',
        default => 'string',
      };
      $settings = match ($type) {
        'list_string' => ['allowed_values' => $question['#options']],
        'datetime' => ['datetime_type' => 'date'],
        'string' => ['max_length' => $question['#maxlength'] ?? 255],
        default => [],
      };
      if (!FieldStorageConfig::loadByName('node', $name)) {
        FieldStorageConfig::create([
          'field_name' => $name,
          'entity_type' => 'node',
          'type' => $type,
          'settings' => $settings,
          'cardinality' => $question['#type'] === 'checkboxes' ? -1 : 1,
          'translatable' => FALSE,
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'customer_request', $name)) {
        FieldConfig::create([
          'field_name' => $name,
          'entity_type' => 'node',
          'bundle' => 'customer_request',
          'label' => $question['#title'],
          'description' => implode(', ', array_intersect_key(EventQuestions::eventOptions(), array_flip($question['events']))),
          // Requiredness depends on the selected event, not the whole bundle.
          'required' => FALSE,
          'settings' => $type === 'integer' ? ['min' => $question['#min'] ?? 1] : [],
        ])->save();
      }
      $widget = match ($type) {
        'list_string' => 'options_buttons',
        'datetime' => 'datetime_default',
        'integer' => 'number',
        'string_long' => 'string_textarea',
        default => 'string_textfield',
      };
      $formatter = match ($type) {
        'list_string' => 'list_default',
        'datetime' => 'datetime_custom',
        'integer' => 'number_integer',
        'string_long' => 'basic_string',
        default => 'string',
      };
      if ($form && !$form->getComponent($name)) {
        $form->setComponent($name, ['type' => $widget, 'weight' => $weight, 'settings' => $type === 'string_long' ? ['rows' => $question['#rows'] ?? 4] : []]);
      }
      if ($view && !$view->getComponent($name)) {
        $view->setComponent($name, ['type' => $formatter, 'label' => 'above', 'weight' => $weight, 'settings' => $type === 'datetime' ? ['date_format' => 'Y-m-d', 'timezone_override' => ''] : []]);
      }
      $weight++;
    }
    // The old text block remains a generated projection for tiles and mail.
    $form?->removeComponent('details')->save();
    $view?->removeComponent('details')->save();
  }

  /**
   * Persists only the active section, then derives list/mail text and date sort.
   */
  public static function synchronize(NodeInterface $node): void {
    $event = $node->get('event')->value;
    $lines = [];
    $date = '';
    foreach (self::definitions() as $name => $question) {
      if (!$node->hasField($name)) {
        continue;
      }
      $active = in_array($event, $question['events'], TRUE);
      if (isset($question['other_for'])) {
        $active = $active && in_array('other', array_column($node->get($question['other_for'])->getValue(), 'value'), TRUE);
      }
      if (!$active) {
        $node->set($name, []);
        continue;
      }
      $values = array_column($node->get($name)->getValue(), 'value');
      if ($question['key'] === 'preferred_date') {
        $date = $values[0] ?? '';
      }
      $values = array_map(static fn($value) => $question['#options'][$value] ?? $value, $values);
      if ($values) {
        $lines[] = ($question['questions'][$event]['#title'] ?? $question['#title']) . ': ' . implode(', ', $values);
      }
    }
    $node->set('details', implode("\n", $lines));
    $node->set('planned_date_sort', PlannedDate::sortKey('Preferred date: ' . $date));
  }

  /**
   * Shows the same event-specific questions on the native node edit form.
   */
  public static function alterForm(array &$form): void {
    // Target native widgets directly rather than assuming public-form names.
    // Field widgets can have different input names and nesting on node forms.
    $form['#attached']['library'][] = 'core/drupal.states';
    if (isset($form['event']['widget'])) {
      $form['event']['widget']['#attributes']['data-customer-request-event'] = 'true';
    }
    foreach (self::definitions() as $name => $question) {
      if (!isset($form[$name])) {
        continue;
      }
      $form[$name]['#attributes']['data-customer-request-answer'] = $name;
      $conditions = [];
      foreach ($question['events'] as $event) {
        if ($conditions) {
          $conditions[] = 'or';
        }
        $conditions[] = [':input[data-customer-request-event]' => ['value' => $event]];
      }
      if (isset($question['other_for'])) {
        $selector = '[data-customer-request-answer="' . $question['other_for'] . '"] :input[value="other"]';
        $conditions = [$conditions, 'and', [$selector => ['checked' => TRUE]]];
      }
      $form[$name]['#states']['visible'] = $conditions;
      if (!empty($question['#event_required']) || isset($question['other_for'])) {
        $form[$name]['#states']['required'] = $conditions;
      }
      if ($question['key'] === 'arrival_time') {
        $form[$name]['widget'][0]['value']['#attributes']['type'] = 'time';
      }
    }
    $form['#validate'][] = [self::class, 'validateForm'];
  }

  /**
   * Enforces active-section requiredness server-side, including Other answers.
   */
  public static function validateForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->getFormObject()->buildEntity($form, $form_state);
    foreach (self::definitions() as $name => $question) {
      $event = $node->get('event')->value;
      if (!in_array($event, $question['events'], TRUE) || !$node->hasField($name)) {
        continue;
      }
      $required = !empty($question['questions'][$event]['#event_required']);
      if (isset($question['other_for'])) {
        $required = in_array('other', array_column($node->get($question['other_for'])->getValue(), 'value'), TRUE);
      }
      if ($required && $node->get($name)->isEmpty()) {
        $form_state->setErrorByName($name, t('@question: kötelező válaszolni.', ['@question' => $question['#title']]));
      }
      $value = $node->get($name)->value;
      if ($question['key'] === 'arrival_time' && $value !== NULL && $value !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) {
        $form_state->setErrorByName($name, t('Kérjük, érvényes időpontot adjatok meg (ÓÓ:PP).'));
      }
    }
  }

}
