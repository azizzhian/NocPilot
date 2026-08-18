<?php

namespace App\Console\Commands;

use App\Models\AppUpdate;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RecordDeployCommand extends Command
{
    protected $signature = 'app:record-deploy
        {from? : Commit hash sebelum pull}
        {to? : Commit hash setelah pull}
        {--branch= : Branch yang di-deploy}';

    protected $description = 'Catat commit range dari deploy ke database (notifikasi update aplikasi)';

    public function handle(): int
    {
        $repoRoot = realpath(base_path('../..'));
        if (! $repoRoot || ! is_dir($repoRoot.'/.git')) {
            $this->warn('Bukan git repo — lewati pencatatan deploy.');

            return self::SUCCESS;
        }

        $from = $this->argument('from') ?: null;
        $to = $this->argument('to') ?: $this->gitRevParse($repoRoot, 'HEAD');

        if (! $to) {
            $this->error('Tidak dapat menentukan commit target.');

            return self::FAILURE;
        }

        if ($from && $from === $to) {
            $this->info('Tidak ada commit baru — lewati pencatatan deploy.');

            return self::SUCCESS;
        }

        $changes = $this->collectCommits($repoRoot, $from, $to);
        if ($changes === []) {
            $this->info('Tidak ada commit dalam range — lewati pencatatan deploy.');

            return self::SUCCESS;
        }

        AppUpdate::query()->create([
            'from_commit' => $from,
            'to_commit' => $to,
            'branch' => $this->option('branch'),
            'changes' => $changes,
            'deployed_at' => now(),
        ]);

        $this->info('Deploy tercatat: '.count($changes).' commit.');

        return self::SUCCESS;
    }

    /** @return list<array{hash: string, message: string, author: string, date: string}> */
    private function collectCommits(string $repoRoot, ?string $from, string $to): array
    {
        $range = $from ? "{$from}..{$to}" : $to;
        $output = $this->runGit($repoRoot, ['log', $range, '--pretty=format:%H|%s|%an|%aI', '--reverse']);

        if ($output === '') {
            return [];
        }

        $changes = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode('|', $line, 4);
            if (count($parts) < 4) {
                continue;
            }

            [$hash, $message, $author, $date] = $parts;
            $changes[] = [
                'hash' => $hash,
                'message' => $message,
                'author' => $author,
                'date' => $date,
            ];
        }

        return $changes;
    }

    private function gitRevParse(string $repoRoot, string $ref): ?string
    {
        $output = $this->runGit($repoRoot, ['rev-parse', $ref]);

        return $output !== '' ? $output : null;
    }

    private function runGit(string $repoRoot, array $args): string
    {
        $process = new Process(array_merge(['git', '-C', $repoRoot], $args));
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }
}
