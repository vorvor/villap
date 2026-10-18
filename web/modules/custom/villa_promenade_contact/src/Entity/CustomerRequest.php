<?php

namespace Drupal\villa_promenade_contact\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\villa_promenade_contact\CustomerRequestViewsData;
use Drupal\villa_promenade_contact\EventQuestions;
use Drupal\villa_promenade_contact\Form\CustomerRequestForm;
use Drupal\villa_promenade_contact\Form\CustomerRequestDeleteForm;

/**
 * Stores private customer enquiries submitted through the event contact form.
 */
#[ContentEntityType(
  id: 'villa_customer_request',
  label: new TranslatableMarkup('Customer request'),
  label_collection: new TranslatableMarkup('Customer requests'),
  label_singular: new TranslatableMarkup('customer request'),
  label_plural: new TranslatableMarkup('customer requests'),
  base_table: 'villa_customer_request',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
  handlers: [
    'access' => EntityAccessControlHandler::class,
    'view_builder' => EntityViewBuilder::class,
    'views_data' => CustomerRequestViewsData::class,
    'list_builder' => EntityListBuilder::class,
    'form' => [
      'edit' => CustomerRequestForm::class,
      'delete' => CustomerRequestDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  admin_permission: 'manage villa customer requests',
  links: [
    'canonical' => '/admin/content/customer-requests/{villa_customer_request}',
    'edit-form' => '/admin/content/customer-requests/{villa_customer_request}/edit',
    'delete-form' => '/admin/content/customer-requests/{villa_customer_request}/delete',
  ],
)]
final class CustomerRequest extends ContentEntityBase {

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
      ->setSetting('allowed_values', EventQuestions::eventOptions() + ['event3' => 'event3 (legacy)', 'event4' => 'event4 (legacy)', 'event5' => 'event5 (legacy)'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 2])
      ->setDisplayOptions('view', ['type' => 'list_default', 'label' => 'above', 'weight' => 2]);
    $fields['request_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Request status'))
      ->setRequired(TRUE)
      ->setDefaultValue('new')
      ->setSetting('allowed_values', [
        'new' => 'New',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 3])
      ->setDisplayOptions('view', ['type' => 'list_default', 'label' => 'above', 'weight' => 3]);
    $fields['details'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Event details'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 4])
      ->setDisplayOptions('view', ['type' => 'basic_string', 'label' => 'above', 'weight' => 4]);
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
