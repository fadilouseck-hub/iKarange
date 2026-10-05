<?php

/**
 * App singleton – manages organization context and settings.
 */
class App
{
    private static ?App $instance = null;
    private ?array $org = null;

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function loadOrg(int $orgId): void
    {
        $stmt = db()->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        $this->org = $stmt->fetch() ?: null;
    }

    public function getOrg(): ?array
    {
        return $this->org;
    }

    public function getOrgName(): string
    {
        return $this->org['name'] ?? "I'KARANGE";
    }
}
