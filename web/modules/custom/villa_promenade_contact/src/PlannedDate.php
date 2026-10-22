<?php

namespace Drupal\villa_promenade_contact;

/**
 * Extracts a chronological sort key from the stored event answers.
 */
final class PlannedDate {

  /**
   * Places requests without a valid exact date after dated requests.
   */
  public const UNKNOWN = 99991231;

  /**
   * Returns a YYYYMMDD key without guessing dates from free-form prose.
   */
  public static function sortKey(string $details): int {
    preg_match_all('/^\h*(?:Tervezett dátum|Mikor ünnepeltek\?|Preferred date)\h*:\h*(\d{4})-(\d{2})-(\d{2})\h*\r?$/imu', $details, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $year = (int) $match[1];
      $month = (int) $match[2];
      $day = (int) $match[3];
      if (checkdate($month, $day, $year)) {
        return $year * 10000 + $month * 100 + $day;
      }
    }
    return self::UNKNOWN;
  }

}
