<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Middleware;

use OCA\Talk\Controller\RecordingController;
use OCA\TalkAutoShare\Service\AutoShareService;
use OCA\TalkAutoShare\Service\Configuration;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Shares recordings once Talk's recording "store" request has succeeded.
 *
 * Registered globally so it sees Talk's controller. Both upload paths end in that request: a direct
 * multipart upload, and the chunked upload (Talk 24+) where the file was assembled through a public
 * WebDAV share beforehand and only `fileName` is posted. Talk has created its "recording stored"
 * notification by then, so it is dismissed right away.
 */
class RecordingStoreMiddleware extends Middleware {
	/** Talk controller action the recording server calls once a recording is uploaded (::class does not autoload). */
	public const STORE_CONTROLLER = RecordingController::class;
	public const STORE_METHOD = 'store';

	private ?int $startedAt = null;

	public function __construct(
		private AutoShareService $service,
		private Configuration $config,
		private IRequest $request,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	public function beforeController($controller, $methodName): void {
		if ($this->isStoreRequest($controller, $methodName)) {
			$this->startedAt = $this->timeFactory->getTime();
		}
	}

	public function afterController($controller, $methodName, Response $response): Response {
		if (!$this->isStoreRequest($controller, $methodName) || $response->getStatus() !== Http::STATUS_OK) {
			return $response;
		}

		try {
			$this->handleStoredRecording();
		} catch (\Throwable $e) {
			// Never break the upload; the user can still share from the notification.
			$this->logger->error('Recording auto-share failed', ['exception' => $e, 'token' => $this->request->getParam('token')]);
		}
		return $response;
	}

	private function isStoreRequest(object $controller, string $methodName): bool {
		return $methodName === self::STORE_METHOD && is_a($controller, self::STORE_CONTROLLER);
	}

	private function handleStoredRecording(): void {
		if (!$this->config->isTalkAvailable()) {
			return;
		}
		$token = (string)$this->request->getParam('token', '');
		$owner = (string)$this->request->getParam('owner', '');
		$fileName = $this->request->getParam('fileName');
		if (!is_string($fileName) || $fileName === '') {
			$fileName = (string)($this->request->getUploadedFile('file')['name'] ?? '');
		}
		if ($token === '' || $owner === '' || $fileName === '') {
			return;
		}

		$file = $this->service->findRecording($owner, $token, $fileName);
		if ($file === null) {
			$this->logger->info('Stored recording not found', ['token' => $token, 'owner' => $owner, 'fileName' => $fileName]);
			return;
		}

		$result = $this->service->shareRecording($file, $owner, $token);
		// Either way the recording is in the chat, so the "share to chat?" notification is obsolete.
		if ($result === AutoShareService::RESULT_SHARED || $result === AutoShareService::RESULT_ALREADY_SHARED) {
			$now = $this->timeFactory->getTime();
			$this->service->dismissStoredNotification($owner, $token, $this->startedAt ?? $now, $now);
		}
	}
}
