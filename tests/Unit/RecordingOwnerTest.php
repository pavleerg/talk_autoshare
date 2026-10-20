<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Tests\Unit;

use OCA\TalkAutoShare\Service\RecordingOwner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecordingOwnerTest extends TestCase {
	private static function p(string $actorType, string $actorId, int $participantType): array {
		return ['actorType' => $actorType, 'actorId' => $actorId, 'participantType' => $participantType];
	}

	public static function cases(): array {
		$owner = self::p('users', 'alice', 1);
		$moderator = self::p('users', 'bob', 2);
		$user = self::p('users', 'carol', 3);
		$guest = self::p('guests', 'g1', 4);
		$guestModerator = self::p('guests', 'g2', 6);

		return [
			'starter is moderator' => [$moderator, [$owner, $moderator, $user], 'bob'],
			'starter is owner' => [$owner, [$owner, $moderator], 'alice'],
			'starter is guest, falls back to owner' => [$guest, [$moderator, $owner, $guest], 'alice'],
			'starter is plain user, falls back to owner' => [$user, [$user, $owner], 'alice'],
			'no starter' => [null, [$user, $owner], 'alice'],
			'no owner, first moderator' => [$guest, [$user, $moderator, self::p('users', 'dave', 2)], 'bob'],
			'guest moderator never qualifies' => [$guestModerator, [$guestModerator, $user, $guest], null],
			'nobody can record' => [$user, [$user, $guest], null],
			'non-user owner (federated) skipped' => [null, [self::p('federated_users', 'x@remote', 1), $moderator], 'bob'],
		];
	}

	#[DataProvider('cases')]
	public function testChoose(?array $starter, array $participants, ?string $expected): void {
		$this->assertSame($expected, RecordingOwner::choose($starter, $participants)['actorId'] ?? null);
	}
}
