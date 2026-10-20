<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

/**
 * Recording formats Talk 24 accepts (RecordingService::DEFAULT_ALLOWED_RECORDING_FORMATS).
 */
final class RecordingFormats {
	public const EXTENSIONS = ['ogg', 'ogv', 'mp4', 'webm', 'mkv'];

	public static function isRecording(string $fileName): bool {
		return in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
	}
}
