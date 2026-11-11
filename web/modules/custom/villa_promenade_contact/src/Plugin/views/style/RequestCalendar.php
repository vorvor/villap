<?php

namespace Drupal\villa_promenade_contact\Plugin\views\style;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Displays dated customer requests in an accessible monthly calendar.
 */
#[ViewsStyle(
  id: 'villa_request_calendar',
  title: new TranslatableMarkup('Customer request calendar'),
  help: new TranslatableMarkup('Monthly calendar of new and approved customer requests.'),
  display_types: ['normal'],
)]
final class RequestCalendar extends StylePluginBase {

  protected $usesFields = TRUE;

  protected $usesGrouping = FALSE;

  /**
   * Returns the selected month in the current user's timezone.
   */
  private function month(): \DateTimeImmutable {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $input = \Drupal::request()->query->get('month');
    if (is_string($input) && preg_match('/^(19|20|21)\d{2}-(0[1-9]|1[0-2])$/D', $input)) {
      return new \DateTimeImmutable($input . '-01', $timezone);
    }
    return (new \DateTimeImmutable('@' . \Drupal::time()->getRequestTime()))->setTimezone($timezone)->modify('first day of this month')->setTime(0, 0);
  }

  /**
   * Keeps the database result limited to this month plus undated requests.
   */
  public function query() {
    parent::query();
    $query = $this->view->query;
    if (!$query instanceof Sql) {
      return;
    }
    $preferred = $query->ensureTable('node__cr_preferred_date');
    $confirmed = $query->ensureTable('node__real_date');
    $status = $query->ensureTable('node__request_status');
    $date = "CASE WHEN $status.request_status_value = 'approved' THEN COALESCE(NULLIF($confirmed.real_date_value, ''), $preferred.cr_preferred_date_value) ELSE $preferred.cr_preferred_date_value END";
    $month = $this->month();
    $query->addWhereExpression(0, "(($date) >= :calendar_start AND ($date) < :calendar_end) OR ($date) IS NULL OR ($date) = ''", [
      ':calendar_start' => $month->format('Y-m-d'),
      ':calendar_end' => $month->modify('+1 month')->format('Y-m-d'),
    ]);
  }

  /**
   * Always show the calendar and month navigation, including empty months.
   */
  public function evenEmpty() {
    return TRUE;
  }

  /**
   * Builds calendar cells using access-checked native field formatters.
   */
  public function render() {
    $month = $this->month();
    $today = (new \DateTimeImmutable('@' . \Drupal::time()->getRequestTime()))->setTimezone($month->getTimezone());
    $route = $this->view->getUrl();
    $link = static function (\DateTimeImmutable $date) use ($route): string {
      $url = clone $route;
      $url->setOption('query', ['month' => $date->format('Y-m')]);
      return $url->toString();
    };
    $cache = (new CacheableMetadata())
      ->setCacheContexts(['url.query_args:month', 'timezone', 'user.permissions', 'user.node_grants:view'])
      ->setCacheTags(['node_list:customer_request'])
      ->setCacheMaxAge(0);
    $builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $days = [];
    $undated = [];
    foreach ($this->view->result as $row) {
      $node = $row->_entity;
      if (!$node instanceof NodeInterface || $node->bundle() !== 'customer_request') {
        continue;
      }
      $access = $node->access('view', NULL, TRUE);
      $cache->addCacheableDependency($node)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $state = $node->get('request_status')->value;
      if (!in_array($state, ['new', 'approved'], TRUE)) {
        continue;
      }
      $date_field = $state === 'approved' && !$node->get('real_date')->isEmpty() ? 'real_date' : 'cr_preferred_date';
      $date_access = $node->get($date_field)->access('view', NULL, TRUE);
      $cache->addCacheableDependency($date_access);
      if (!$date_access->isAllowed()) {
        continue;
      }
      $date = $node->get($date_field)->value;
      $item = [
        '#type' => 'container',
        '#attributes' => ['class' => ['request-calendar__event', 'request-calendar__event--' . $state]],
      ];
      foreach (['title', 'event', 'cr_guest_count'] as $field) {
        $item[$field] = $builder->viewField($node->get($field), [
          'label' => $field === 'cr_guest_count' ? 'inline' : 'hidden',
          'type' => match ($field) {
            'event' => 'list_default',
            'cr_guest_count' => 'number_integer',
            default => 'string',
          },
          'settings' => $field === 'title' ? ['link_to_entity' => TRUE] : [],
        ]);
      }
      $item['state'] = ['#type' => 'html_tag', '#tag' => 'span', '#attributes' => ['class' => ['request-calendar__state']], '#value' => $state === 'approved' ? $this->t('Approved') : $this->t('New')];
      if (!$date) {
        $undated[] = $item;
      }
      elseif (str_starts_with($date, $month->format('Y-m') . '-')) {
        $days[$date][] = $item;
      }
    }
    $weeks = [];
    $start = $month->modify('-' . ((int) $month->format('N') - 1) . ' days');
    $last = $month->modify('last day of this month');
    $end = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
      $date = $day->format('Y-m-d');
      $weeks[intdiv((int) $start->diff($day)->days, 7)][] = [
        'date' => $date,
        'number' => $day->format('j'),
        'outside' => $day->format('Y-m') !== $month->format('Y-m'),
        'today' => $date === $today->format('Y-m-d'),
        'events' => $days[$date] ?? [],
      ];
    }
    $build = [
      '#theme' => 'villa_request_calendar',
      '#month_label' => (new DrupalDateTime($month->format('Y-m-d'), $month->getTimezone()))->format('F Y'),
      '#selected_month' => $month->format('Y-m'),
      '#previous_url' => $month->format('Y-m') > '1900-01' ? $link($month->modify('-1 month')) : NULL,
      '#next_url' => $month->format('Y-m') < '2199-12' ? $link($month->modify('+1 month')) : NULL,
      '#today_url' => $link($today),
      '#action_url' => $route->toString(),
      '#weekdays' => [$this->t('Monday'), $this->t('Tuesday'), $this->t('Wednesday'), $this->t('Thursday'), $this->t('Friday'), $this->t('Saturday'), $this->t('Sunday')],
      '#weeks' => $weeks,
      '#undated' => $undated,
      '#attached' => ['library' => ['villa_promenade_contact/request-calendar']],
    ];
    $cache->applyTo($build);
    return $build;
  }

}
