<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

trait BackofficeCsrfRequest
{
    /** @var array<int, string> */
    private array $backofficeCsrfTokens = [];

    /**
     * @param array<string, mixed> $parameters
     */
    private function post(KernelBrowser $client, string $uri, array $parameters = []): Crawler
    {
        $clientId = spl_object_id($client);
        if (!isset($this->backofficeCsrfTokens[$clientId])) {
            $crawler = $client->request('GET', '/app');
            self::assertResponseIsSuccessful();

            $tokenField = $crawler->filter('form[action="/deconnexion"] input[name="_token"]');
            self::assertCount(1, $tokenField);
            $token = $tokenField->attr('value');
            self::assertIsString($token);
            $this->backofficeCsrfTokens[$clientId] = $token;
        }

        return $client->request('POST', $uri, [
            '_token' => $this->backofficeCsrfTokens[$clientId],
            ...$parameters,
        ]);
    }
}
