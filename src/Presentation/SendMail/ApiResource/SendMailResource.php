<?php

declare(strict_types=1);

namespace App\Presentation\SendMail\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Presentation\SendMail\Dto\SendMailInput;
use App\Presentation\SendMail\State\SendMailProcessor;
use ArrayObject;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @codeCoverageIgnore
 */
#[ApiResource(
    shortName: 'SendMail',
    operations: [
        new Post(
            uriTemplate: '/util/send-mail',
            status: 204,
            openapi: new Model\Operation(
                summary: 'Send an email through the API',
                description: "Allows sending an email by providing the sender's details and message content.",
                requestBody: new Model\RequestBody(
                    description: 'Payload containing the sender information and message details',
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'example' => 'John Doe'],
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                                    'subject' => ['type' => 'string', 'example' => 'Hello'],
                                    'message' => ['type' => 'string', 'example' => 'This is a test message'],
                                ],
                                'required' => ['name', 'email', 'subject', 'message'],
                            ],
                        ],
                    ])
                ),
            ),
            input: SendMailInput::class,
            name: 'send-mail',
            processor: SendMailProcessor::class,
        ),
    ],
)]
final class SendMailResource
{
    #[Groups(['send_mail:write'])]
    public string $name;

    #[Groups(['send_mail:write'])]
    public string $email;

    #[Groups(['send_mail:write'])]
    public string $subject;

    #[Groups(['send_mail:write'])]
    public string $message;
}
