<?php

namespace Tests\Unit;

use App\Services\IssueService;
use PHPUnit\Framework\TestCase;

class IssueServiceSortingTest extends TestCase
{
    public function test_sort_matching_issues_by_natural_title_order(): void
    {
        $service = new IssueService();

        $issues = collect([
            ['id' => 4, 'title' => '4. Dynamic project creation with lara...', 'status' => 'todo', 'matching_tag_count' => 3, 'updated_at' => '2026-08-03 09:00:00'],
            ['id' => 1, 'title' => '1. Implementation plan for portfolio...', 'status' => 'todo', 'matching_tag_count' => 3, 'updated_at' => '2026-08-03 08:00:00'],
            ['id' => 2, 'title' => '2. tweak 1 29 May 26 - MMRahman', 'status' => 'todo', 'matching_tag_count' => 3, 'updated_at' => '2026-08-03 07:00:00'],
            ['id' => 10, 'title' => '10. Future expansion', 'status' => 'todo', 'matching_tag_count' => 1, 'updated_at' => '2026-08-03 06:00:00'],
        ]);

        $sorted = $service->sortMatchingIssues($issues);

        $this->assertSame([1, 2, 4, 10], $sorted->pluck('id')->all());
    }

    public function test_unnumbered_matching_issues_fall_back_to_latest_updated_order(): void
    {
        $service = new IssueService();

        $issues = collect([
            ['id' => 30, 'title' => 'Zoo follow-up', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-01 09:00:00'],
            ['id' => 31, 'title' => 'Alpha follow-up', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-03 09:00:00'],
            ['id' => 32, 'title' => 'Beta triage', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-02 09:00:00'],
        ]);

        $sorted = $service->sortMatchingIssues($issues);

        $this->assertSame([31, 32, 30], $sorted->pluck('id')->all());
    }

    public function test_numbered_and_unnumbered_matching_issues_use_numbered_sequence_then_latest_updated_fallback(): void
    {
        $service = new IssueService();

        $issues = collect([
            ['id' => 40, 'title' => 'Beta triage', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-03 09:00:00'],
            ['id' => 41, 'title' => '2. Follow-up task', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-01 09:00:00'],
            ['id' => 42, 'title' => '1. Initial draft', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-02 09:00:00'],
            ['id' => 43, 'title' => 'Alpha follow-up', 'status' => 'todo', 'matching_tag_count' => 2, 'updated_at' => '2026-08-04 09:00:00'],
        ]);

        $sorted = $service->sortMatchingIssues($issues);

        $this->assertSame([42, 41, 43, 40], $sorted->pluck('id')->all());
    }
}
