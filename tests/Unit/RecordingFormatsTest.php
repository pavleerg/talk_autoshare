<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Tests\Unit;

use OCA\TalkAutoShare\Service\RecordingFormats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecordingFormatsTest extends TestCase {
	public static function fileNames(): array {
		return [
			'video webm' => ['Recording 2026-10-20 10-00-00.webm', true],
			'audio ogg' => ['call.ogg', true],
			'ogv' => ['call.ogv', true],
			'mp4' => ['call.mp4', true],
			'uppercase extension' => ['call.MKV', true],
			'transcript' => ['call.md', false],
			'summary' => ['call - summary.md', false],
			'other media' => ['call.avi', false],
			'no extension' => ['webm', false],
		];
	}

	#[DataProvider('fileNames')]
	public function testIsRecording(string $fileName, bool $expected): void {
		$this->assertSame($expected, RecordingFormats::isRecording($fileName));
	}
}
