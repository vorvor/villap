<?php

namespace Drupal\Tests\hts\Unit;

use Drupal\hts\Service\HtsBefShared;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests exposed identifier value extraction.
 */
#[Group('hts')]
class HtsBefSharedTest extends UnitTestCase {

  /**
   * Tests nested IDs, sentinels, and unrelated input isolation.
   */
  public function testSelectedTermIds(): void {
    $helper = new HtsBefShared();
    $input = [
      'topics' => [
        'hierarchical_term_selector' => [
          'elements' => ['10', '_none', ['20']],
        ],
      ],
      999 => '999',
    ];

    $this->assertSame([10, 20], $helper->selectedTermIds($input['topics']));
  }

  /**
   * Tests Drupal entity-autocomplete label values.
   */
  public function testSelectedTermIdsParsesAutocompleteLabels(): void {
    $helper = new HtsBefShared();

    $this->assertSame([37], $helper->selectedTermIds('HTS Leaf A (37)'));
  }

}
