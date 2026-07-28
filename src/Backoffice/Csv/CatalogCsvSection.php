<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use Symfony\Component\String\UnicodeString;

enum CatalogCsvSection: string
{
    case Ranks = 'ranks';
    case RoleStats = 'role-stats';
    case WelcomeMessages = 'welcome-messages';
    case ByeMessages = 'bye-messages';
    case Roles = 'roles';
    case Stats = 'stats';
    case Elements = 'elements';

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return match ($this) {
            self::Ranks => ['nom', 'pourcentage', 'titre_depart', 'est_staff'],
            self::RoleStats => ['role', 'stat', 'pourcentage'],
            self::WelcomeMessages, self::ByeMessages => ['rang', 'message'],
            self::Roles => ['nom', 'pourcentage', 'emoji'],
            self::Stats => ['nom'],
            self::Elements => ['nom', 'emoji'],
        };
    }

    /**
     * @return list<string>
     */
    public function requiredHeaders(): array
    {
        return match ($this) {
            self::Ranks, self::Roles => ['nom', 'pourcentage'],
            self::RoleStats => ['role', 'stat', 'pourcentage'],
            self::WelcomeMessages, self::ByeMessages => ['rang', 'message'],
            self::Stats, self::Elements => ['nom'],
        };
    }

    /**
     * @return array<string, 'string'|'percentage'|'boolean'>
     */
    public function columnTypes(): array
    {
        return match ($this) {
            self::Ranks => [
                'nom' => 'string',
                'pourcentage' => 'percentage',
                'titre_depart' => 'string',
                'est_staff' => 'boolean',
            ],
            self::RoleStats => [
                'role' => 'string',
                'stat' => 'string',
                'pourcentage' => 'percentage',
            ],
            self::WelcomeMessages, self::ByeMessages => [
                'rang' => 'string',
                'message' => 'string',
            ],
            self::Roles => [
                'nom' => 'string',
                'pourcentage' => 'percentage',
                'emoji' => 'string',
            ],
            self::Stats => ['nom' => 'string'],
            self::Elements => ['nom' => 'string', 'emoji' => 'string'],
        };
    }

    /**
     * @return list<string>
     */
    public function naturalKeyColumns(): array
    {
        return match ($this) {
            self::Ranks, self::Roles, self::Stats, self::Elements => ['nom'],
            self::RoleStats => ['role', 'stat'],
            self::WelcomeMessages, self::ByeMessages => ['rang', 'message'],
        };
    }

    /**
     * @param array<string, string|int|bool|null> $values
     */
    public function naturalKey(array $values): string
    {
        $parts = [];
        foreach ($this->naturalKeyColumns() as $column) {
            $value = (string) ($values[$column] ?? '');
            $parts[] = 'message' === $column
                ? trim($value)
                : (new UnicodeString($value))->trim()->lower()->toString();
        }

        return 1 === \count($parts) ? $parts[0] : implode("\x1F", $parts);
    }

    /**
     * @return list<array<string, string>>
     */
    public function sampleRows(): array
    {
        return match ($this) {
            self::Ranks => [
                ['nom' => 'Novice', 'pourcentage' => '65', 'titre_depart' => 'Novice sur le départ', 'est_staff' => 'non'],
                ['nom' => 'Gardien', 'pourcentage' => '35', 'titre_depart' => 'Gardien sur le départ', 'est_staff' => 'oui'],
            ],
            self::RoleStats => [
                ['role' => 'Guerrier', 'stat' => 'Force', 'pourcentage' => '60'],
                ['role' => 'Guerrier', 'stat' => 'Agilité', 'pourcentage' => '40'],
                ['role' => 'Oracle', 'stat' => 'Force', 'pourcentage' => '100'],
            ],
            self::WelcomeMessages => [
                ['rang' => 'Novice', 'message' => 'Bienvenue parmi nous, {user}.'],
                ['rang' => 'Gardien', 'message' => 'Le staff accueille {user}.'],
            ],
            self::ByeMessages => [
                ['rang' => 'Novice', 'message' => 'À bientôt, {user}.'],
                ['rang' => 'Gardien', 'message' => 'Merci pour ton aide, {user}.'],
            ],
            self::Roles => [
                ['nom' => 'Guerrier', 'pourcentage' => '55', 'emoji' => '⚔️'],
                ['nom' => 'Oracle', 'pourcentage' => '45', 'emoji' => '🔮'],
            ],
            self::Stats => [
                ['nom' => 'Force'],
                ['nom' => 'Agilité'],
            ],
            self::Elements => [
                ['nom' => 'Feu', 'emoji' => '🔥'],
                ['nom' => 'Eau', 'emoji' => '💧'],
            ],
        };
    }

    public function exampleFilename(): string
    {
        return match ($this) {
            self::Ranks => 'exemple-rangs.csv',
            self::RoleStats => 'exemple-probabilites-role-stat.csv',
            self::WelcomeMessages => 'exemple-messages-arrivee.csv',
            self::ByeMessages => 'exemple-messages-depart.csv',
            self::Roles => 'exemple-roles.csv',
            self::Stats => 'exemple-stats.csv',
            self::Elements => 'exemple-elements.csv',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ranks => 'Rangs',
            self::RoleStats => 'Probabilités rôle/stat',
            self::WelcomeMessages => 'Messages d’arrivée',
            self::ByeMessages => 'Messages de départ',
            self::Roles => 'Rôles de personnage',
            self::Stats => 'Stats',
            self::Elements => 'Éléments',
        };
    }
}
