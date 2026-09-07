<?php

namespace App\Jobs;

use App\Models\Issue;
use App\Services\IssueNarrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateIssueNarration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        private readonly int $issueId,
        private readonly bool $force = false,
    ) {
    }

    public function handle(IssueNarrationService $service): void
    {
        $issue = Issue::withoutGlobalScope('user_owned')->find($this->issueId);

        if (! $issue) {
            return;
        }

        try {
            $service->generate($issue, $this->force);
        } catch (\Throwable) {
            // generate() already persists the failure state on the record.
        }
    }
}
