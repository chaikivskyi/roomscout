<?php

namespace App\Tests\Application\Identity;

use ApiPlatform\Symfony\Bundle\Test\Client;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

trait InteractsWithPasswordReset
{
    protected function requestPasswordResetToken(Client $client, string $email): string
    {
        $client->request('POST', '/api/forgot-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => $email],
        ]);

        self::assertResponseStatusCodeSame(202);
        self::assertQueuedEmailCount(1);

        $mail = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $mail);

        $html = (string) $mail->getHtmlBody();
        self::assertMatchesRegularExpression('/token=[0-9a-f]{64}/', $html);
        preg_match('/token=([0-9a-f]{64})/', $html, $matches);

        return $matches[1];
    }
}
