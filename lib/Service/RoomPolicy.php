<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

/**
 * Decides which conversations get recordings auto-shared.
 *
 *  - "listed" (default): only the room tokens configured by the admin
 *  - "group": every conversation except one-to-one calls
 */
final class RoomPolicy {
	public const MODE_LISTED = 'listed';
	public const MODE_GROUP = 'group';

	/** Talk's Room::TYPE_ONE_TO_ONE and ::TYPE_ONE_TO_ONE_FORMER. */
	private const ONE_TO_ONE_TYPES = [1, 5];

	/** @param list<string> $tokens */
	public function __construct(
		private string $mode,
		private array $tokens,
	) {
	}

	public static function fromConfig(string $mode, string $rawTokens): self {
		return new self(
			$mode === self::MODE_GROUP ? self::MODE_GROUP : self::MODE_LISTED,
			self::parseTokens($rawTokens),
		);
	}

	/** @return list<string> */
	public static function parseTokens(string $raw): array {
		$tokens = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		return array_values(array_unique($tokens));
	}

	public function getMode(): string {
		return $this->mode;
	}

	/** @return list<string> */
	public function getTokens(): array {
		return $this->tokens;
	}

	/** @param int $roomType Talk's Room::TYPE_* */
	public function isAllowed(string $token, int $roomType): bool {
		if ($this->mode === self::MODE_GROUP) {
			return !in_array($roomType, self::ONE_TO_ONE_TYPES, true);
		}
		return in_array($token, $this->tokens, true);
	}
}
