<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Listener;

use OCA\Talk\Events\CallStartedEvent;
use OCA\TalkAutoShare\Service\AutoRecordService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Starts recording when a call starts; Talk dispatches the event once per call, in the starter's join request.
 *
 * @template-implements IEventListener<Event>
 */
class CallStartedListener implements IEventListener {
	public function __construct(
		private AutoRecordService $service,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof CallStartedEvent) {
			return;
		}
		try {
			$this->service->startForCall($event->getRoom(), $event->getActor());
		} catch (\Throwable $e) {
			// Never break joining the call; recording can still be started manually.
			$this->logger->error('Automatic recording failed', ['exception' => $e, 'token' => $event->getRoom()->getToken()]);
		}
	}
}
