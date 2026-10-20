<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCP\IUserManager;
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
		private IUserManager $userManager,
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
		// A disabled or deleted owner could not store the recording, so they never qualify.
		$starterCandidate = $starter === null ? null : self::candidate($starter);
		if ($starterCandidate !== null && !$this->isActiveUser($starterCandidate['actorId'])) {
			$starterCandidate = null;
		}
		$owner = RecordingOwner::choose(
			$starterCandidate,
			array_values(array_filter(
				array_map(self::candidate(...), $users),
				fn (array $candidate): bool => $this->isActiveUser($candidate['actorId']),
			)),
		);
		if ($owner === null) {
			$this->logger->info('No moderator to own an automatic recording', ['token' => $token]);
			return;
		}

		// start() posts to the recording server synchronously (Talk retries up to 3 times), so a slow
		// server delays the starter's join, just like Talk's own record button. Deliberate: under
		// mod_php nothing can run after the response is sent, and a cron job would start too late.
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

	private function isActiveUser(string $userId): bool {
		return $this->userManager->get($userId)?->isEnabled() ?? false;
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
