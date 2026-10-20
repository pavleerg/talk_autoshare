<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

use OCA\TalkAutoShare\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IAppConfig;

/**
 * The app's admin settings, and whether the Talk version it relies on is available.
 */
class Configuration {
	private const MIN_TALK_MAJOR = 24;

	public function __construct(
		private IAppConfig $appConfig,
		private IAppManager $appManager,
	) {
	}

	public function isTalkAvailable(): bool {
		if (!$this->appManager->isEnabledForAnyone('spreed') || !class_exists(\OCA\Talk\Manager::class)) {
			return false;
		}
		$major = (int)explode('.', $this->appManager->getAppVersion('spreed'))[0];
		return $major >= self::MIN_TALK_MAJOR;
	}

	public function getPolicy(): RoomPolicy {
		return RoomPolicy::fromConfig(
			$this->appConfig->getValueString(Application::APP_ID, 'mode', RoomPolicy::MODE_LISTED),
			$this->appConfig->getValueString(Application::APP_ID, 'room_tokens', ''),
		);
	}

	public function setPolicy(RoomPolicy $policy): void {
		$this->appConfig->setValueString(Application::APP_ID, 'mode', $policy->getMode());
		$this->appConfig->setValueString(Application::APP_ID, 'room_tokens', implode(',', $policy->getTokens()));
	}

	public function isAutoRecordEnabled(): bool {
		return $this->appConfig->getValueString(Application::APP_ID, 'auto_record', 'no') === 'yes';
	}

	public function setAutoRecordEnabled(bool $enabled): void {
		$this->appConfig->setValueString(Application::APP_ID, 'auto_record', $enabled ? 'yes' : 'no');
	}
}
