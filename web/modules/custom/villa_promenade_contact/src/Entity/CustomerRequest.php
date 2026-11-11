<?php

namespace Drupal\villa_promenade_contact\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\villa_promenade_contact\EventQuestions;
use Drupal\villa_promenade_contact\PlannedDate;

/**
 * Retains the retired schema without exposing old records or management routes.
 */
#[ContentEntityType(
  id: 'villa_customer_request',
  label: new TranslatableMarkup('Legacy customer request'),
  label_collection: new TranslatableMarkup('Legacy customer requests'),
  label_singular: new TranslatableMarkup('customer request'),
  label_plural: new TranslatableMarkup('customer requests'),
  base_table: 'villa_customer_request',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
  handlers: [
    'access' => EntityAccessControlHandler::class,
  ],
  admin_permission: 'manage villa customer requests',
)]
final class CustomerRequest extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    throw new \LogicException('Legacy customer request storage is retired. Use customer_request nodes.');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Customer name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayOptions('view', ['type' => 'string', 'label' => 'above', 'weight' => 0]);
    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 1])
      ->setDisplayOptions('view', ['type' => 'basic_string', 'label' => 'above', 'weight' => 1]);
    $fields['event'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Event'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', EventQuestions::eventOptions())
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 2])
      ->setDisplayOptions('view', ['type' => 'list_default', 'label' => 'above', 'weight' => 2]);
    $fields['request_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Request status'))
      ->setRequired(TRUE)
      ->setDefaultValue('new')
      ->setSetting('allowed_values', [
        'new' => 'New',
        'approved' => 'Approved',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'archive' => 'Archive',
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 3])
      ->setDisplayOptions('view', ['type' => 'list_default', 'label' => 'above', 'weight' => 3]);
    $fields['details'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Event details'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 4])
      ->setDisplayOptions('view', ['type' => 'basic_string', 'label' => 'above', 'weight' => 4]);
    $fields['planned_date_sort'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Tervezett dátum'))
      ->setDescription(t('Internal chronological key derived from the event details. Undated requests sort last.'))
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(PlannedDate::UNKNOWN)
      ->setReadOnly(TRUE);
    $fields['admin_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Internal notes'))
      ->setDescription(t('Only request managers can access these notes.'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 5])
      ->setDisplayOptions('view', ['type' => 'basic_string', 'label' => 'above', 'weight' => 5]);
    $fields['is_demo'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Demo request'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', ['type' => 'boolean', 'label' => 'above', 'settings' => ['format' => 'yes-no'], 'weight' => 6]);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Submitted'))
      ->setDisplayOptions('view', ['type' => 'timestamp', 'label' => 'above', 'weight' => 7]);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Last updated'));
    return $fields;
  }

}
