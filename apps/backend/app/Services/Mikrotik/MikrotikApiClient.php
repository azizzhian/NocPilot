<?php

namespace App\Services\Mikrotik;

use RuntimeException;

class MikrotikApiClient
{
    /** @var resource|null */
    private $socket;

    public function connect(string $host, int $port, string $user, string $pass, int $timeout = 5): void
    {
        $this->disconnect();

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $socket) {
            throw new RuntimeException("Gagal konek ke {$host}:{$port} — {$errstr}");
        }

        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;

        $this->login($user, $pass);
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    public function query(string $command, array $attributes = []): array
    {
        $this->writeSentence(array_merge([$command], $this->formatAttributes($attributes), ['']));

        return $this->readAll();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function login(string $user, string $pass): void
    {
        $response = $this->query('/login', ['name' => $user, 'password' => $pass]);

        if ($this->sentenceHasTrap($response)) {
            $challenge = $this->extractChallenge($response);
            if ($challenge) {
                $hashed = md5(chr(0).$pass.pack('H*', $challenge));
                $response = $this->query('/login', [
                    'name' => $user,
                    'response' => '00'.$hashed,
                ]);
            }
        }

        if ($this->sentenceHasTrap($response)) {
            throw new RuntimeException($this->trapMessage($response) ?? 'Autentikasi MikroTik gagal.');
        }
    }

    private function formatAttributes(array $attributes): array
    {
        $words = [];

        foreach ($attributes as $key => $value) {
            if (is_int($key)) {
                $words[] = (string) $value;
            } else {
                $words[] = '='.$key.'='.$value;
            }
        }

        return $words;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readAll(): array
    {
        $sentences = [];

        while (true) {
            $sentence = $this->readSentence();
            if ($sentence === []) {
                break;
            }

            $parsed = $this->parseSentence($sentence);
            $sentences[] = $parsed;

            if (($parsed['!type'] ?? '') === 'done') {
                break;
            }
        }

        return $sentences;
    }

    /**
     * @return array<int, string>
     */
    private function readSentence(): array
    {
        $sentence = [];

        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }

            $sentence[] = $word;
        }

        return $sentence;
    }

    /**
     * @param  array<int, string>  $words
     */
    private function parseSentence(array $words): array
    {
        $parsed = [];

        foreach ($words as $word) {
            if (! str_starts_with($word, '=')) {
                $parsed['!type'] = ltrim($word, '!');

                continue;
            }

            $word = substr($word, 1);
            $eq = strpos($word, '=');
            if ($eq === false) {
                continue;
            }

            $parsed[substr($word, 0, $eq)] = substr($word, $eq + 1);
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<string, string>>  $sentences
     */
    private function sentenceHasTrap(array $sentences): bool
    {
        foreach ($sentences as $sentence) {
            if (($sentence['!type'] ?? null) === 'trap' || isset($sentence['message'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, string>>  $sentences
     */
    private function extractChallenge(array $sentences): ?string
    {
        foreach ($sentences as $sentence) {
            if (isset($sentence['ret'])) {
                return $sentence['ret'];
            }
            if (isset($sentence['=ret'])) {
                return $sentence['=ret'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, string>>  $sentences
     */
    private function trapMessage(array $sentences): ?string
    {
        foreach ($sentences as $sentence) {
            if (($sentence['!type'] ?? null) === 'trap' || isset($sentence['message'])) {
                return $sentence['message'] ?? $sentence['=message'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $words
     */
    private function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord((string) $word);
        }
    }

    private function writeWord(string $word): void
    {
        $length = strlen($word);
        $this->writeLength($length);
        if ($length > 0) {
            $this->writeRaw($word);
        }
    }

    private function readWord(): string
    {
        $length = $this->readLength();
        if ($length === 0) {
            return '';
        }

        return $this->readRaw($length);
    }

    private function writeLength(int $length): void
    {
        if ($length < 0x80) {
            $this->writeRaw(chr($length));
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $this->writeRaw(chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $this->writeRaw(chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $this->writeRaw(chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        } else {
            $this->writeRaw(chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
        }
    }

    private function readLength(): int
    {
        $byte = ord($this->readRaw(1));

        if ($byte < 0x80) {
            return $byte;
        }

        if (($byte & 0xC0) === 0x80) {
            return (($byte & 0x3F) << 8) + ord($this->readRaw(1));
        }

        if (($byte & 0xE0) === 0xC0) {
            return (($byte & 0x1F) << 16) + (ord($this->readRaw(1)) << 8) + ord($this->readRaw(1));
        }

        if (($byte & 0xF0) === 0xE0) {
            return (($byte & 0x0F) << 24) + (ord($this->readRaw(1)) << 16) + (ord($this->readRaw(1)) << 8) + ord($this->readRaw(1));
        }

        if (($byte & 0xF8) === 0xF0) {
            return (ord($this->readRaw(1)) << 24) + (ord($this->readRaw(1)) << 16) + (ord($this->readRaw(1)) << 8) + ord($this->readRaw(1));
        }

        return 0;
    }

    private function writeRaw(string $data): void
    {
        $written = fwrite($this->socket, $data);
        if ($written === false) {
            throw new RuntimeException('Gagal menulis ke socket MikroTik.');
        }
    }

    private function readRaw(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Koneksi MikroTik terputus.');
            }
            $data .= $chunk;
        }

        return $data;
    }
}
