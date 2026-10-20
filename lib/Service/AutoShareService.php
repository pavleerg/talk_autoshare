<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Service;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager as RoomManager;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Shares a stored recording into its conversation, as the recording owner.
 *
 * Runs inside Talk's recording "store" request. Talk's share listener posts the "file_shared" chat
 * message itself, attributed to the session user, for every route except its own share-chat route,
 * so RecordingService::shareToChat() would post it twice. Instead we create the share as the owner
 * via a volatile session user and let the listener post the message.
 *
 * Talk classes are internal (not OCP) API and are resolved lazily; retest after every Talk upgrade.
 */
class AutoShareService {
	public const RESULT_SHARED = 'shared';
	public const RESULT_ALREADY_SHARED = 'already_shared';
	public const RESULT_NOT_OPTED_IN = 'not_opted_in';
	public const RESULT_SKIPPED = 'skipped';

	public function __construct(
		private ContainerInterface $container,
		private Configuration $config,
		private IShareManager $shareManager,
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private INotificationManager $notificationManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Locate a recording Talk just stored: <recording folder>/<room token>/<file name> of the owner.
	 *
	 * Transcripts and summaries (.md) are stored in the same folder and never match.
	 */
	public function findRecording(string $owner, string $token, string $fileName): ?File {
		$fileName = basename($fileName);
		if (!RecordingFormats::isRecording($fileName)) {
			return null;
		}
		$folder = trim($this->container->get(TalkConfig::class)->getRecordingFolder($owner), '/');
		try {
			$node = $this->rootFolder->getUserFolder($owner)
				->get(ltrim($folder . '/' . $token . '/' . $fileName, '/'));
		} catch (NotFoundException) {
			return null;
		}
		return $node instanceof File ? $node : null;
	}

	/** @return self::RESULT_* */
	public function shareRecording(File $file, string $owner, string $token): string {
		try {
			$room = $this->container->get(RoomManager::class)->getRoomByToken($token);
		} catch (RoomNotFoundException) {
			$this->logger->info('Room of recording does not exist', ['token' => $token]);
			return self::RESULT_SKIPPED;
		}

		if (!$this->config->getPolicy()->isAllowed($token, $room->getType())) {
			$this->logger->debug('Room not opted in for recording auto-share', ['token' => $token]);
			return self::RESULT_NOT_OPTED_IN;
		}

		$user = $this->userManager->get($owner);
		if ($user === null) {
			return self::RESULT_SKIPPED;
		}
		try {
			$this->container->get(ParticipantService::class)->getParticipant($room, $owner);
		} catch (ParticipantNotFoundException) {
			$this->logger->info('Recording owner is not a participant', ['token' => $token, 'owner' => $owner]);
			return self::RESULT_SKIPPED;
		}

		foreach ($this->shareManager->getSharesBy($owner, IShare::TYPE_ROOM, $file, false, -1) as $existing) {
			if ($existing->getSharedWith() === $token) {
				return self::RESULT_ALREADY_SHARED;
			}
		}

		// Same share as RecordingService::shareToChat() creates.
		$share = $this->shareManager->newShare();
		$share->setNodeId($file->getId())
			->setNode($file)
			->setShareTime($this->timeFactory->getDateTime())
			->setSharedBy($owner)
			->setShareType(IShare::TYPE_ROOM)
			->setSharedWith($token)
			->setPermissions(Constants::PERMISSION_READ);

		$previousUser = $this->userSession->getUser();
		$this->userSession->setVolatileActiveUser($user);
		try {
			$this->shareManager->createShare($share);
		} finally {
			$this->userSession->setVolatileActiveUser($previousUser);
		}

		$this->logger->info('Auto-shared call recording to chat', ['token' => $token, 'fileId' => $file->getId(), 'owner' => $owner]);
		return self::RESULT_SHARED;
	}

	/**
	 * Dismiss Talk's "recording stored – share to chat?" notification.
	 *
	 * Notifications are matched by exact timestamp, so try every second of the store request.
	 */
	public function dismissStoredNotification(string $owner, string $token, int $from, int $to): void {
		for ($timestamp = $from; $timestamp <= $to; $timestamp++) {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp('spreed')
				->setUser($owner)
				->setObject('recording', $token)
				->setDateTime($this->timeFactory->getDateTime('@' . $timestamp))
				->setSubject('record_file_stored');
			$this->notificationManager->markProcessed($notification);
		}
	}
}
