<?php

declare(strict_types=1);

namespace App\Presentation\SendMail\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class SendMailInput
{
    #[Groups(['send_mail:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public string $name;

    #[Groups(['send_mail:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 100)]
    public string $email;

    #[Groups(['send_mail:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 100)]
    public string $subject;

    #[Groups(['send_mail:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 1000)]
    public string $message;
}
