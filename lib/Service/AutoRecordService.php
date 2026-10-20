<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Starts a video recording when a call starts in a conversation that auto-share handles.
 *
 * Talk stops the recording itself when the call ends; the upload is then auto-shared as usual.
 * Talk classes are internal (not OCP) API and are resolved lazily; retest after every Talk upgrade.
 */
class AutoRecordService {
	public function __construct(
		private ContainerInterface $container,
		private Configuration $config,
		private LoggerInterface $logger,
	) {
	}

	public function startForCall(Room $room, ?Participant $starter): void {
		if (!$this->config->isAutoRecordEnabled() || !$this->config->isTalkAvailable() || $room->isFederatedConversation()) {
			return;
		}
		$token = $room->getToken();
		if (!$this->config->getPolicy()->isAllowed($token, $room->getType())) {
			$this->logger->debug('Room not opted in for automatic recording', ['token' => $token]);
			return;
		}

		$users = $this->container->get(ParticipantService::class)->getParticipantsByActorType($room, RecordingOwner::ACTOR_USERS);
		$owner = RecordingOwner::choose(
			$starter === null ? null : self::candidate($starter),
			array_map(self::candidate(...), $users),
		);
		if ($owner === null) {
			$this->logger->info('No moderator to own an automatic recording', ['token' => $token]);
			return;
		}

		try {
			$this->container->get(RecordingService::class)
				->start($room, Room::RECORDING_VIDEO, $owner['actorId'], $owner['participant']);
		} catch (\InvalidArgumentException $e) {
			// e.g. recording disabled in Talk, or already running
			$this->logger->info('Automatic recording not started: ' . $e->getMessage(), ['token' => $token]);
			return;
		}
		$this->logger->info('Started automatic call recording', ['token' => $token, 'owner' => $owner['actorId']]);
	}

	/** @return array{actorType: string, actorId: string, participantType: int, participant: Participant} */
	private static function candidate(Participant $participant): array {
		$attendee = $participant->getAttendee();
		return [
			'actorType' => $attendee->getActorType(),
			'actorId' => $attendee->getActorId(),
			'participantType' => $attendee->getParticipantType(),
			'participant' => $participant,
		];
	}
}
