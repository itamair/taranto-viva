<?php

declare(strict_types=1);

namespace Drupal\Tests\warmer\Unit\Plugin\QueueWorker;

use Drupal\Tests\UnitTestCase;
use Drupal\warmer\Plugin\QueueWorker\ItemWarmer;
use Drupal\warmer\QueueData;

/**
 * @coversDefaultClass \Drupal\warmer\Plugin\QueueWorker\ItemWarmer
 * @group warmer
 */
class ItemWarmerTest extends UnitTestCase {

  /**
   * Processes the items queued for warming.
   *
   * @var \Drupal\warmer\Plugin\QueueWorker\ItemWarmer
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sut = new ItemWarmer([], 'warmer', []);
  }

  /**
   * The data is processed when appropriate.
   *
   * @covers ::processItem
   */
  public function testProcessItem() {
    $data = $this->prophesize(QueueData::class);
    $data->process()->shouldBeCalledTimes(1);
    $this->sut->processItem($data->reveal());
    $this->sut->processItem(NULL);
  }

}
