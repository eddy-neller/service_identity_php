<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\UseCase\Query\CheckPasswordResetToken\CheckPasswordResetTokenQuery;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\PasswordResetCheckInput;
use App\Presentation\User\State\PasswordResetCheckProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class PasswordResetCheckProcessorTest extends KernelTestCase
{
    private QueryBusInterface&MockObject $queryBus;

    private Operation&MockObject $operation;

    private PasswordResetCheckProcessor $processor;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(QueryBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->processor = new PasswordResetCheckProcessor(
            $this->queryBus
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidPasswordResetCheckInput();
        $output = true;

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($input, $output): bool {
                $this->assertInstanceOf(CheckPasswordResetTokenQuery::class, $query);
                $this->assertSame($input->token, $query->token);

                return $output;
            });

        $this->processor->process($input, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();

        $this->queryBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process($invalidInput, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(null, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process('invalid', $this->operation);
    }

    public function testProcessThrowsLogicExceptionForArrayInput(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(['token' => 'test'], $this->operation);
    }

    public function testProcessThrowsBadRequestHttpExceptionWhenTokenInvalid(): void
    {
        $input = $this->createValidPasswordResetCheckInput();
        $output = false;

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($output);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Token de réinitialisation invalide ou expiré.');

        $this->processor->process($input, $this->operation);
    }

    private function createValidPasswordResetCheckInput(): PasswordResetCheckInput
    {
        $input = new PasswordResetCheckInput();
        $input->token = 'valid-reset-token-123';

        return $input;
    }
}
