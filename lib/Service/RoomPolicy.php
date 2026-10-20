<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

/**
 * Decides which conversations get recordings auto-shared.
 *
 *  - "listed" (default): only the room tokens configured by the admin
 *  - "group": every group and public conversation (never one-to-one, changelog or note-to-self)
 */
final class RoomPolicy {
	public const MODE_LISTED = 'listed';
	public const MODE_GROUP = 'group';

	/** Talk's Room::TYPE_GROUP and ::TYPE_PUBLIC; an allowlist so future room types stay excluded. */
	private const GROUP_TYPES = [2, 3];

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
			return in_array($roomType, self::GROUP_TYPES, true);
		}
		return in_array($token, $this->tokens, true);
	}
}
