<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Proposals\Models\KnowledgeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

/**
 * The Knowledge Base page.
 *
 * The entry flashcards are paginated server-side, and the search box filters
 * them by words in the title, description or type. Every card opens a detail
 * modal for the selected entry, so a keyword search narrows the flashcards
 * and clicking one shows its full answer.
 */
class KnowledgeBaseBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->seed(DeallyAccessSeeder::class);

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($acme);
        $provisioner->seed($acme);

        $membership = $this->alice()->memberships()->active()->with('tenant')->first();

        app('db')->purge('deally');
        app(TenantConnectionBinder::class)->bind($membership->tenant);
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /* ---------- helpers ---------- */

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }

    private function createEntries(int $count): void
    {
        KnowledgeEntry::factory()->count($count)->create([
            'type' => 'product',
        ]);
    }

    private function fetchHtml(array $query = []): string
    {
        return $this->actingAs($this->alice())
            ->get(route('deally.kb.index', $query))
            ->assertOk()
            ->getContent();
    }

    private function cardCount(string $html): int
    {
        return substr_count($html, 'class="kb-card"');
    }

    /* ---------- pagination ---------- */

    public function test_entries_are_paginated_across_pages(): void
    {
        $this->createEntries(14); // 4 seeded entries + 14 = 18 total

        $page1 = $this->fetchHtml();
        $this->assertSame(12, $this->cardCount($page1), 'page one should hold one full page');
        $this->assertStringContainsString('1–12 of 18', $page1);

        $page2 = $this->fetchHtml(['page' => 2]);
        $this->assertSame(6, $this->cardCount($page2), 'page two should hold the remainder');
        $this->assertStringContainsString('13–18 of 18', $page2);
    }

    public function test_a_single_page_of_entries_shows_no_pagination(): void
    {
        $html = $this->fetchHtml();

        $this->assertSame(4, $this->cardCount($html));
        $this->assertStringNotContainsString('kb-pagination', $html);
    }

    /* ---------- search ---------- */

    public function test_search_matches_keywords_in_titles_descriptions_and_types(): void
    {
        // Matches the seeded "Enterprise Suite" description (SSO keyword).
        $html = $this->fetchHtml(['search' => 'SSO']);
        $this->assertStringContainsString('kb-card-title">Enterprise Suite', $html);
        $this->assertStringNotContainsString('kb-card-title">Enterprise Tier Pricing', $html);

        // Matches the seeded "Cisco" entry via its competitor type.
        $html = $this->fetchHtml(['search' => 'competitor']);
        $this->assertStringContainsString('kb-card-title">Cisco', $html);
        $this->assertStringNotContainsString('kb-card-title">Enterprise Suite', $html);
    }

    public function test_search_is_kept_when_paging_through_results(): void
    {
        KnowledgeEntry::factory()->count(15)->create([
            'type' => 'product',
            'title' => 'Initech Product Options',
        ]);

        $page1 = $this->fetchHtml(['search' => 'Initech']);
        $this->assertSame(12, $this->cardCount($page1));
        $this->assertStringContainsString('search=Initech', $page1);

        $page2 = $this->fetchHtml(['search' => 'Initech', 'page' => 2]);
        $this->assertSame(3, $this->cardCount($page2));
        $this->assertStringContainsString('search=Initech', $page2);
    }

    public function test_search_with_no_matches_shows_the_empty_state(): void
    {
        $html = $this->fetchHtml(['search' => 'xyzabcnothing']);

        $this->assertSame(0, $this->cardCount($html));
        $this->assertStringContainsString('No entries match &quot;xyzabcnothing&quot;.', $html);
    }

    /* ---------- click-to-view ---------- */

    public function test_every_card_opens_the_selected_entry_in_a_view_modal(): void
    {
        $html = $this->fetchHtml();

        $this->assertStringContainsString('data-open-modal="modal-viewkb"', $html);
        $this->assertStringContainsString('modal-viewkb', $html);
        $this->assertStringContainsString('data-fill="title"', $html);
        $this->assertStringContainsString('data-fill="desc"', $html);
    }
}
