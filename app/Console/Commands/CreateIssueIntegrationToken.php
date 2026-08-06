<?php

namespace App\Console\Commands;

use App\Models\IssueIntegrationToken;
use App\Models\User;
use Illuminate\Console\Command;

class CreateIssueIntegrationToken extends Command
{
    protected $signature = 'issues:integration-token {user_id : The user account that will own created issues} {--name=AI integration : A label for this token}';

    protected $description = 'Create a bearer token for the AI issue integration API';

    public function handle(): int
    {
        $user = User::query()->find($this->argument('user_id'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        [, $plainTextToken] = IssueIntegrationToken::issue($user, (string) $this->option('name'));

        $this->warn('Copy this token now; it is not stored in plain text.');
        $this->line($plainTextToken);

        return self::SUCCESS;
    }
}
