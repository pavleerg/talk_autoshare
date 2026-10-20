<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Controller;

use OCA\TalkAutoShare\AppInfo\Application;
use OCA\TalkAutoShare\Service\Configuration;
use OCA\TalkAutoShare\Service\RoomPolicy;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/** Admin-only (default for controllers without #[NoAdminRequired]), CSRF-checked. */
class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private Configuration $config,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function save(string $mode = RoomPolicy::MODE_LISTED, string $tokens = '', bool $autoRecord = false): RedirectResponse {
		$this->config->setPolicy(RoomPolicy::fromConfig($mode, $tokens));
		$this->config->setAutoRecordEnabled($autoRecord);

		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'talk']) . '#talk_autoshare'
		);
	}
}
