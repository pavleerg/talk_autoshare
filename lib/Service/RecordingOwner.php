<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

/**
 * Picks the user who owns an automatically started recording (it is stored in their Files).
 *
 * Talk only lets logged-in moderators start recordings, so the owner is the call starter if they are
 * one, otherwise the conversation owner, otherwise the first moderator. Guest moderators never qualify.
 */
final class RecordingOwner {
	/** Talk's Participant::OWNER and ::MODERATOR. */
	public const TYPE_OWNER = 1;
	public const TYPE_MODERATOR = 2;
	public const ACTOR_USERS = 'users';

	/**
	 * Candidates may carry extra keys (e.g. the Talk participant); the chosen one is returned as is.
	 *
	 * @template T of array{actorType: string, actorId: string, participantType: int}
	 * @param T|null $starter
	 * @param list<T> $participants
	 * @return T|null
	 */
	public static function choose(?array $starter, array $participants): ?array {
		if ($starter !== null && self::canRecord($starter)) {
			return $starter;
		}
		foreach ([self::TYPE_OWNER, self::TYPE_MODERATOR] as $type) {
			foreach ($participants as $participant) {
				if ($participant['participantType'] === $type && self::canRecord($participant)) {
					return $participant;
				}
			}
		}
		return null;
	}

	/** @param array{actorType: string, actorId: string, participantType: int} $participant */
	private static function canRecord(array $participant): bool {
		return $participant['actorType'] === self::ACTOR_USERS
			&& in_array($participant['participantType'], [self::TYPE_OWNER, self::TYPE_MODERATOR], true);
	}
}
