<?php

final class DatabaseSessionHandler implements SessionHandlerInterface {
    private PDO $db;
    private int $lifetime;

    public function __construct(PDO $db, int $lifetime) {
        $this->db = $db;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false {
        $stmt = $this->db->prepare(
            'SELECT payload FROM app_sessions WHERE id = ? AND expires_at > CURRENT_TIMESTAMP LIMIT 1'
        );
        $stmt->execute([$id]);
        $payload = $stmt->fetchColumn();
        return $payload === false ? '' : (string) $payload;
    }

    public function write(string $id, string $data): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $this->lifetime);
        $stmt = $this->db->prepare(
            'INSERT INTO app_sessions (id, payload, expires_at) VALUES (?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload, expires_at = EXCLUDED.expires_at'
        );
        return $stmt->execute([$id, $data, $expiresAt]);
    }

    public function destroy(string $id): bool {
        $stmt = $this->db->prepare('DELETE FROM app_sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $maxLifetime): int|false {
        return $this->db->exec('DELETE FROM app_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
    }
}
