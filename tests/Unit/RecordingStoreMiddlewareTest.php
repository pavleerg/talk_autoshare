<?php

declare(strict_types=1);

namespace OCA\TalkAutoShare\Tests\Unit;

use OCA\TalkAutoShare\Middleware\RecordingStoreMiddleware;
use OCA\TalkAutoShare\Service\AutoShareService;
use OCA\TalkAutoShare\Service\Configuration;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// Stand-in for Talk's controller, matched by class name.
if (!class_exists(\OCA\Talk\Controller\RecordingController::class)) {
	eval('namespace OCA\Talk\Controller; class RecordingController {}');
}

class RecordingStoreMiddlewareTest extends TestCase {
	private AutoShareService&MockObject $service;
	private IRequest&MockObject $request;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;
	private RecordingStoreMiddleware $middleware;
	private object $talkController;

	protected function setUp(): void {
		$this->service = $this->createMock(AutoShareService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$config = $this->createMock(Configuration::class);
		$config->method('isTalkAvailable')->willReturn(true);
		$this->middleware = new RecordingStoreMiddleware($this->service, $config, $this->request, $this->timeFactory, $this->logger);
		$this->talkController = new \OCA\Talk\Controller\RecordingController();
	}

	private function params(array $params): void {
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => $params[$key] ?? $default,
		);
	}

	private function dispatch(object $controller, string $method, int $status = Http::STATUS_OK): DataResponse {
		$response = new DataResponse(null, $status);
		$this->middleware->beforeController($controller, $method);
		$this->assertSame($response, $this->middleware->afterController($controller, $method, $response));
		return $response;
	}

	public function testIgnoresOtherControllers(): void {
		$other = new \stdClass();
		$this->service->expects($this->never())->method('findRecording');
		$this->dispatch($other, 'store');
	}

	public function testIgnoresOtherMethods(): void {
		$this->service->expects($this->never())->method('findRecording');
		$this->dispatch($this->talkController, 'requestUpload');
	}

	public function testIgnoresFailedStore(): void {
		$this->service->expects($this->never())->method('findRecording');
		$this->dispatch($this->talkController, 'store', Http::STATUS_BAD_REQUEST);
	}

	public function testChunkedUploadSharesAndDismissesOverRequestWindow(): void {
		$this->params(['token' => 'abcd1234', 'owner' => 'alice', 'fileName' => 'call.webm']);
		$this->timeFactory->method('getTime')->willReturnOnConsecutiveCalls(1000, 1003);
		$file = $this->createMock(File::class);

		$this->service->expects($this->once())->method('findRecording')->with('alice', 'abcd1234', 'call.webm')->willReturn($file);
		$this->service->expects($this->once())->method('shareRecording')->with($file, 'alice', 'abcd1234')->willReturn(AutoShareService::RESULT_SHARED);
		$this->service->expects($this->once())->method('dismissStoredNotification')->with('alice', 'abcd1234', 1000, 1003);

		$this->dispatch($this->talkController, 'store');
	}

	public function testDirectUploadUsesUploadedFileName(): void {
		$this->params(['token' => 'abcd1234', 'owner' => 'alice']);
		$this->request->method('getUploadedFile')->with('file')->willReturn(['name' => 'call.ogg', 'tmp_name' => '/tmp/x']);
		$this->timeFactory->method('getTime')->willReturn(1000);

		$this->service->expects($this->once())->method('findRecording')->with('alice', 'abcd1234', 'call.ogg')->willReturn(null);
		$this->service->expects($this->never())->method('shareRecording');

		$this->dispatch($this->talkController, 'store');
	}

	public function testDismissesWhenAlreadyShared(): void {
		$this->params(['token' => 'abcd1234', 'owner' => 'alice', 'fileName' => 'call.webm']);
		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->service->method('findRecording')->willReturn($this->createMock(File::class));
		$this->service->method('shareRecording')->willReturn(AutoShareService::RESULT_ALREADY_SHARED);

		$this->service->expects($this->once())->method('dismissStoredNotification')->with('alice', 'abcd1234', 1000, 1000);
		$this->dispatch($this->talkController, 'store');
	}

	public function testDoesNotDismissWhenNotShared(): void {
		$this->params(['token' => 'abcd1234', 'owner' => 'alice', 'fileName' => 'call.webm']);
		$this->service->method('findRecording')->willReturn($this->createMock(File::class));
		$this->service->method('shareRecording')->willReturn(AutoShareService::RESULT_NOT_OPTED_IN);

		$this->service->expects($this->never())->method('dismissStoredNotification');
		$this->dispatch($this->talkController, 'store');
	}

	public function testErrorsAreLoggedAndResponseKept(): void {
		$this->params(['token' => 'abcd1234', 'owner' => 'alice', 'fileName' => 'call.webm']);
		$this->service->method('findRecording')->willThrowException(new \RuntimeException('boom'));

		$this->logger->expects($this->once())->method('error');
		$this->dispatch($this->talkController, 'store');
	}
}
