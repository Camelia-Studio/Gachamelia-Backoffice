<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class CatalogCsvDraftStore
{
    private const string SESSION_KEY = 'gachamelia.catalog_csv_drafts';
    private const int LIFETIME_SECONDS = 1800;

    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function put(
        int $userId,
        string $targetType,
        string $targetId,
        CatalogCsvSection $section,
        array $payload,
        ?\DateTimeImmutable $now = null,
    ): string {
        $now ??= new \DateTimeImmutable();
        $drafts = $this->cleanExpired($this->drafts(), $now);
        $token = bin2hex(random_bytes(32));
        $drafts[$token] = [
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'section' => $section->value,
            'expires_at' => $now->getTimestamp() + self::LIFETIME_SECONDS,
            'payload' => $payload,
        ];
        $this->session()->set(self::SESSION_KEY, $drafts);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(
        string $token,
        int $userId,
        string $targetType,
        string $targetId,
        CatalogCsvSection $section,
        ?\DateTimeImmutable $now = null,
    ): ?array {
        $now ??= new \DateTimeImmutable();
        $drafts = $this->cleanExpired($this->drafts(), $now);
        $this->session()->set(self::SESSION_KEY, $drafts);
        $draft = $drafts[$token] ?? null;
        if (!\is_array($draft)
            || $draft['user_id'] !== $userId
            || $draft['target_type'] !== $targetType
            || $draft['target_id'] !== $targetId
            || $draft['section'] !== $section->value
            || !\is_array($draft['payload'] ?? null)
        ) {
            return null;
        }

        return $draft['payload'];
    }

    public function remove(string $token): void
    {
        $drafts = $this->drafts();
        unset($drafts[$token]);
        $this->session()->set(self::SESSION_KEY, $drafts);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function drafts(): array
    {
        $drafts = $this->session()->get(self::SESSION_KEY, []);

        return \is_array($drafts) ? $drafts : [];
    }

    /**
     * @param array<string, array<string, mixed>> $drafts
     *
     * @return array<string, array<string, mixed>>
     */
    private function cleanExpired(array $drafts, \DateTimeImmutable $now): array
    {
        foreach ($drafts as $token => $draft) {
            if (!\is_int($draft['expires_at'] ?? null) || $draft['expires_at'] <= $now->getTimestamp()) {
                unset($drafts[$token]);
            }
        }

        return $drafts;
    }

    private function session(): SessionInterface
    {
        $session = $this->requestStack->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }
}
