<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Settings;

use OCA\TalkAutoShare\AppInfo\Application;
use OCA\TalkAutoShare\Service\Configuration;
use OCA\TalkAutoShare\Service\RoomPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private Configuration $config,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		$policy = $this->config->getPolicy();
		return new TemplateResponse(Application::APP_ID, 'admin', [
			'mode' => $policy->getMode(),
			'tokens' => implode("\n", $policy->getTokens()),
			'talkAvailable' => $this->config->isTalkAvailable(),
			'autoRecord' => $this->config->isAutoRecordEnabled(),
			'saveUrl' => $this->urlGenerator->linkToRoute(Application::APP_ID . '.settings.save'),
			'modeListed' => RoomPolicy::MODE_LISTED,
			'modeGroup' => RoomPolicy::MODE_GROUP,
		], '');
	}

	public function getSection(): string {
		return 'talk';
	}

	public function getPriority(): int {
		return 90;
	}
}
