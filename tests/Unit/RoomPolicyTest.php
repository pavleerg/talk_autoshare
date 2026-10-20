<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Tests\Unit;

use OCA\TalkAutoShare\Service\RoomPolicy;
use PHPUnit\Framework\TestCase;

class RoomPolicyTest extends TestCase {
	private const GROUP = 2;
	private const PUBLIC = 3;
	private const ONE_TO_ONE = 1;
	private const ONE_TO_ONE_FORMER = 5;

	public function testParseTokens(): void {
		$this->assertSame(['abc123', 'def456', 'ghi789'], RoomPolicy::parseTokens(" abc123,def456\n\nghi789; abc123 "));
		$this->assertSame([], RoomPolicy::parseTokens(''));
	}

	public function testListedModeOnlyAllowsListedTokens(): void {
		$policy = RoomPolicy::fromConfig('listed', 'abc123,def456');

		$this->assertTrue($policy->isAllowed('abc123', self::GROUP));
		$this->assertTrue($policy->isAllowed('def456', self::ONE_TO_ONE), 'explicitly listed 1:1 rooms are allowed');
		$this->assertFalse($policy->isAllowed('zzz999', self::GROUP));
	}

	public function testDefaultIsListedAndEmpty(): void {
		$policy = RoomPolicy::fromConfig('', '');

		$this->assertSame(RoomPolicy::MODE_LISTED, $policy->getMode());
		$this->assertFalse($policy->isAllowed('abc123', self::GROUP));
	}

	public function testUnknownModeFallsBackToListed(): void {
		$this->assertSame(RoomPolicy::MODE_LISTED, RoomPolicy::fromConfig('everything', '')->getMode());
	}

	public function testGroupModeExcludesOneToOne(): void {
		$policy = RoomPolicy::fromConfig('group', '');

		$this->assertTrue($policy->isAllowed('anytoken', self::GROUP));
		$this->assertTrue($policy->isAllowed('anytoken', self::PUBLIC));
		$this->assertFalse($policy->isAllowed('anytoken', self::ONE_TO_ONE));
		$this->assertFalse($policy->isAllowed('anytoken', self::ONE_TO_ONE_FORMER));
	}
}
