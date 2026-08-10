<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\SendMail;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\Messenger\Message\SendEmailMessage;
use App\Presentation\SendMail\Dto\SendMailInput;
use App\Presentation\SendMail\State\SendMailProcessor;
use App\Presentation\Shared\State\PresentationErrorCode;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SendMailProcessorTest extends TestCase
{
    private SendMailProcessor $sendMailProcessor;

    /** @var MessageBusInterface&MockObject */
    private MockObject $bus;

    /** @var ParameterBagInterface&MockObject */
    private MockObject $parameterBag;

    private SendMailInput $data;

    /** @var Request&MockObject */
    private MockObject $request;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->data = new SendMailInput();
        $this->request = $this->createMock(Request::class);

        $this->sendMailProcessor = new SendMailProcessor(
            $this->bus,
            $this->parameterBag
        );
    }

    public function testProcessWithInvalidDataThrowsLogicException(): void
    {
        $invalidData = new stdClass();

        $this->bus->expects($this->never())
            ->method('dispatch');
        $this->parameterBag->expects($this->never())
            ->method('get');
        $this->request->expects($this->never())
            ->method('getPreferredLanguage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $operation = $this->createStub(Operation::class);
        $this->sendMailProcessor->process($invalidData, $operation);
    }

    public function testProcessSendsEmailSuccessfully(): void
    {
        $operation = $this->createStub(Operation::class);
        $context = ['request' => $this->request];

        $this->data->name = 'John Doe';
        $this->data->email = 'john@example.com';
        $this->data->subject = 'Test Subject';
        $this->data->message = 'Test Message';

        $this->request
            ->expects($this->once())
            ->method('getPreferredLanguage')
            ->with(['en', 'fr'])
            ->willReturn('en');

        $this->parameterBag
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['app.enabled_locales', ['en', 'fr']],
                ['mailer_to', 'admin@example.com'],
            ]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendEmailMessage $message) {
                $this->assertSame('admin@example.com', $message->to);
                $this->assertSame('Message from John Doe: Test Subject', $message->subject);
                $this->assertSame('emails/en_sendmail.html.twig', $message->template);
                $this->assertSame([
                    'name' => 'John Doe',
                    'emailFrom' => 'john@example.com',
                    'subject' => 'Test Subject',
                    'message' => 'Test Message',
                ], $message->context);

                return true;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->sendMailProcessor->process($this->data, $operation, [], $context);
    }

    public function testProcessWithFrenchLanguage(): void
    {
        $operation = $this->createStub(Operation::class);
        $context = ['request' => $this->request];

        $this->data->name = 'Jean Dupont';
        $this->data->email = 'jean@example.com';
        $this->data->subject = 'Sujet Test';
        $this->data->message = 'Message Test';

        $this->request
            ->expects($this->once())
            ->method('getPreferredLanguage')
            ->with(['en', 'fr'])
            ->willReturn('fr');

        $this->parameterBag
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['app.enabled_locales', ['en', 'fr']],
                ['mailer_to', 'admin@example.com'],
            ]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendEmailMessage $message) {
                $this->assertSame('emails/fr_sendmail.html.twig', $message->template);

                return true;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->sendMailProcessor->process($this->data, $operation, [], $context);
    }

    public function testProcessWithNoRequestContextUsesDefaultLanguage(): void
    {
        $operation = $this->createStub(Operation::class);
        $context = [];

        $this->request->expects($this->never())
            ->method('getPreferredLanguage');

        $this->data->name = 'Jane Doe';
        $this->data->email = 'jane@example.com';
        $this->data->subject = 'Test Subject';
        $this->data->message = 'Test Message';

        $this->parameterBag
            ->expects($this->once())
            ->method('get')
            ->with('mailer_to')
            ->willReturn('admin@example.com');

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendEmailMessage $message) {
                $this->assertSame('emails/en_sendmail.html.twig', $message->template);

                return true;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->sendMailProcessor->process($this->data, $operation, [], $context);
    }

    public function testProcessWithRealRequestUsesPreferredLanguage(): void
    {
        $operation = $this->createStub(Operation::class);

        $this->request->expects($this->never())
            ->method('getPreferredLanguage');

        $realRequest = new Request();
        $realRequest->headers->set('Accept-Language', 'en');

        $context = ['request' => $realRequest];

        $this->data->name = 'Bob Smith';
        $this->data->email = 'bob@example.com';
        $this->data->subject = 'Test Subject';
        $this->data->message = 'Test Message';

        $this->parameterBag
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['app.enabled_locales', ['en', 'fr']],
                ['mailer_to', 'admin@example.com'],
            ]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendEmailMessage $message) {
                $this->assertSame('emails/en_sendmail.html.twig', $message->template);

                return true;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->sendMailProcessor->process($this->data, $operation, [], $context);
    }

    public function testProcessWithCorrectEmailContext(): void
    {
        $operation = $this->createStub(Operation::class);
        $context = ['request' => $this->request];

        $this->data->name = 'Alice Johnson';
        $this->data->email = 'alice@example.com';
        $this->data->subject = 'Important Subject';
        $this->data->message = 'Important message content';

        $this->request
            ->expects($this->once())
            ->method('getPreferredLanguage')
            ->with(['en', 'fr'])
            ->willReturn('en');

        $this->parameterBag
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['app.enabled_locales', ['en', 'fr']],
                ['mailer_to', 'admin@example.com'],
            ]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendEmailMessage $message) {
                $this->assertSame('Alice Johnson', $message->context['name']);
                $this->assertSame('alice@example.com', $message->context['emailFrom']);
                $this->assertSame('Important Subject', $message->context['subject']);
                $this->assertSame('Important message content', $message->context['message']);

                return true;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->sendMailProcessor->process($this->data, $operation, [], $context);
    }
}
