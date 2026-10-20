<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\AppInfo;

use OCA\Talk\Events\CallStartedEvent;
use OCA\TalkAutoShare\Listener\CallStartedListener;
use OCA\TalkAutoShare\Listener\RecordingPageListener;
use OCA\TalkAutoShare\Middleware\RecordingStoreMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'talk_autoshare';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Global, so it runs for Talk's controllers too.
		$context->registerMiddleware(RecordingStoreMiddleware::class, true);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, RecordingPageListener::class);
		// ::class does not autoload, so this is safe while Talk is disabled.
		$context->registerEventListener(CallStartedEvent::class, CallStartedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
