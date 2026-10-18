<?php

/**
 * @file
 * Creates clearly marked fictional requests. Run with drush php:script.
 */

$storage = \Drupal::entityTypeManager()->getStorage('villa_customer_request');
$names = ['Anna Demo', 'Balazs Demo', 'Csilla Demo', 'David Demo', 'Eszter Demo', 'Ferenc Demo', 'Gabriella Demo', 'Henrik Demo', 'Ilona Demo', 'Janos Demo'];
$statuses = ['new', 'in_progress', 'completed', 'cancelled'];
$details = [
  "Preferred date: 2027-05-15\nNumber of guests: 80",
  "Company name: Example Company\nRoom arrangement: Boardroom",
  "Occasion: Demo birthday celebration\nCatering preference: Buffet",
  "Arrival date: 2027-06-04\nNumber of nights: 3",
  "Subject: Demo venue enquiry\nAdditional details: Please send example package information.",
];
$transaction = \Drupal::database()->startTransaction();
$created_ids = [];
try {
  foreach ($names as $index => $name) {
    $email = 'demo-request-' . ($index + 1) . '@example.com';
    $existing = $storage->getQuery()->accessCheck(FALSE)
      ->condition('email', $email)
      ->condition('is_demo', TRUE)
      ->execute();
    if ($existing) {
      continue;
    }
    $request = $storage->create([
      'name' => $name,
      'email' => $email,
      'event' => 'event' . ($index % 5 + 1),
      'request_status' => $statuses[$index % count($statuses)],
      'details' => $details[$index % 5],
      'admin_notes' => 'Fictional demo data. Safe to delete after reviewing the admin surface.',
      'is_demo' => TRUE,
      'created' => \Drupal::time()->getCurrentTime() - ($index * 86400),
    ]);
    $violations = $request->validate();
    if ($violations->count()) {
      throw new \RuntimeException((string) $violations);
    }
    $request->save();
    $created_ids[] = $request->id();
  }
}
catch (\Throwable $exception) {
  $transaction->rollBack();
  throw $exception;
}
unset($transaction);
printf("Created %d demo requests. IDs: %s\n", count($created_ids), implode(', ', $created_ids));
