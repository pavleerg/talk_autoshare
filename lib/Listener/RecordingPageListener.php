<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Listener;

use OCA\TalkAutoShare\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Loads js/recording-grid.js on Talk's recording page (the one the recording server's browser opens).
 *
 * Talk's recording view never applies the grid default of the normal UI, so it always records the
 * speaker view; the script switches it to grid.
 *
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class RecordingPageListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}
		$response = $event->getResponse();
		if ($response->getApp() === 'spreed' && $response->getTemplateName() === 'recording') {
			Util::addScript(Application::APP_ID, 'recording-grid');
		}
	}
}
