<?php

namespace Database\Seeders;

use Deally\Calls\Models\Call;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Proposals\Models\Proposal;
use Deally\Retention\Models\RetentionSetting;
use Deally\Tasks\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeallyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owners = $this->ownerIds();

        $oppIds = [];

        foreach ([
            ['company' => 'Acme Corp', 'contact_name' => 'Jane Doe', 'contact_title' => 'CTO', 'packages' => 'Enterprise Suite (3), Add-on X (1)', 'stage' => 'negotiation', 'value' => 48000],
            ['company' => 'Globex Inc', 'contact_name' => 'Mark Lee', 'contact_title' => 'VP Eng', 'packages' => 'Pro Plan (1)', 'stage' => 'won', 'value' => 12500],
            ['company' => 'Wayne Enterprises', 'contact_name' => 'Bruce Wayne', 'contact_title' => 'CEO', 'packages' => 'Enterprise Suite (1)', 'stage' => 'demo', 'value' => 22000],
            ['company' => 'Stark Industries', 'contact_name' => 'Pepper Potts', 'contact_title' => 'COO', 'packages' => 'Pro Plan (2)', 'stage' => 'discovery', 'value' => 18000],
        ] as $row) {
            $opp = Opportunity::query()->create($row + ['owner_user_id' => $owners['company'][$row['company']] ?? null]);
            $oppIds[$opp->company] = $opp->getKey();
        }

        foreach ([
            ['name' => 'Demo & Discovery', 'company' => 'Acme Corp', 'duration' => '24m', 'sentiment' => 'positive', 'contact_name' => 'Jane Doe', 'contact_role' => 'CTO', 'date' => today(), 'opportunity_id' => $oppIds['Acme Corp'] ?? null, 'summary' => 'Jane confirmed Acme is currently using Cisco but frustrated with slow support and rising costs. They need native Slack integration and SSO. Budget approved. Agreed to send an Enterprise proposal with custom onboarding.', 'notes' => 'Customer worried about migration timeline.'],
            ['name' => 'Discovery', 'company' => 'Globex Inc', 'duration' => '18m', 'sentiment' => 'neutral', 'contact_name' => 'Mark Lee', 'contact_role' => 'VP Eng', 'date' => now()->subDays(4), 'opportunity_id' => $oppIds['Globex Inc'] ?? null],
            ['name' => 'Intro Call', 'company' => 'Initech', 'duration' => '12m', 'sentiment' => 'negative', 'contact_name' => 'Sam King', 'contact_role' => 'IT Lead', 'date' => now()->subDays(6)],
        ] as $row) {
            $call = Call::query()->create(['sentiment' => $row['sentiment'] ?? 'neutral', 'status' => Call::STATUS_COMPLETED, 'owner_user_id' => $owners['company'][$row['company']] ?? null] + $row);

            if ($call->name === 'Demo & Discovery') {
                $call->transcriptLines()->createMany([
                    ['speaker' => 'Jane', 'is_agent' => false, 'text' => "We're currently using Cisco, but support has been really slow.", 'linked_type' => 'competitor', 'linked_text' => 'Battle card vs Cisco was shown'],
                    ['speaker' => 'Jane', 'is_agent' => false, 'text' => 'What price should we plan for at around 500 users?', 'sequence' => 1],
                    ['speaker' => 'Agent', 'is_agent' => true, 'text' => 'Enterprise tier covers 500 seats with SSO, Slack integration, and a custom onboarding.', 'sequence' => 2],
                    ['speaker' => 'Jane', 'is_agent' => false, 'text' => 'If that includes support for HIPAA, we can move quickly.', 'sequence' => 3],
                ]);
            }
        }

        foreach ([
            ['title' => 'Review Call — Acme Corp', 'assignee' => 'Alice Johnson', 'linked_company' => 'Acme Corp', 'due_at' => today()->setTime(17, 0), 'status' => 'todo'],
            ['title' => 'Send pricing to Globex', 'assignee' => 'David Chen', 'linked_company' => 'Globex Inc', 'due_at' => today()->setTime(15, 30), 'status' => 'todo'],
            ['title' => 'Prep battle card for Acme', 'assignee' => 'Bob Carter', 'linked_company' => 'Acme Corp', 'due_at' => today()->addDay()->setTime(9, 0), 'status' => 'todo'],
            ['title' => 'Schedule QBR with Wayne', 'assignee' => 'Erica Valdez', 'linked_company' => 'Wayne Enterprises', 'due_at' => today()->addDays(2)->setTime(10, 0), 'status' => 'todo'],
            ['title' => 'Follow up with Initech', 'assignee' => 'Bob Carter', 'linked_company' => 'Initech', 'due_at' => now()->subDays(2)->setTime(14, 0), 'status' => 'todo'],
            ['title' => 'Send spec sheet to Acme', 'assignee' => 'Charlie Lee', 'linked_company' => 'Acme Corp', 'due_at' => now()->subDays(3)->setTime(11, 0), 'status' => 'todo'],
            ['title' => 'Review proposal v2', 'assignee' => 'Alice Johnson', 'linked_company' => 'Acme Corp', 'due_at' => now()->subDay()->setTime(13, 0), 'status' => 'closed'],
            ['title' => 'Send call recap', 'assignee' => 'Erica Valdez', 'linked_company' => 'Globex Inc', 'due_at' => now()->subDays(2)->setTime(16, 30), 'status' => 'closed'],
        ] as $row) {
            Task::query()->create($row + ['owner_user_id' => $owners['name'][$row['assignee']] ?? null]);
        }

        RetentionSetting::query()->updateOrCreate(
            ['id' => 1],
            ['tier' => 'standard', 'months' => 3],
        );

        foreach ([
            ['name' => 'Acme Enterprise v2', 'company' => 'Acme Corp', 'value' => 81000, 'status' => 'viewed', 'package' => 'Enterprise Suite — 500 users', 'quote' => '"As you mentioned during our call, ensuring stability under load is critical."', 'line_items' => [['item' => 'Enterprise Suite — 500 users', 'amount' => '$7,500/mo'], ['item' => 'Custom Onboarding', 'amount' => 'Included']]],
            ['name' => 'Globex Pro Plan', 'company' => 'Globex Inc', 'value' => 12500, 'status' => 'approved', 'package' => 'Pro Plan — 200 users'],
            ['name' => 'Initech Starter', 'company' => 'Initech', 'value' => 6200, 'status' => 'rejected', 'package' => 'Starter — 50 users'],
        ] as $row) {
            Proposal::query()->create($row + ['owner_user_id' => $owners['company'][$row['company']] ?? null]);
        }

        foreach ([
            ['type' => 'product', 'title' => 'Enterprise Suite', 'description' => '500+ users · SSO · Slack integration'],
            ['type' => 'pricing', 'title' => 'Enterprise Tier Pricing', 'description' => '$15/user/mo · Volume discounts beyond 1,000'],
            ['type' => 'competitor', 'title' => 'Cisco', 'description' => 'Strong brand, slow support.'],
            ['type' => 'objection', 'title' => 'Pricing Objection', 'description' => 'Response: highlight volume discounts.'],
        ] as $row) {
            KnowledgeEntry::query()->create($row);
        }

        foreach ([
            ['type' => 'gap', 'text' => 'Do you support HIPAA compliance?', 'source' => 'Acme Demo · '.today()->format('M d').' · Unanswered gap', 'status' => 'pending'],
            ['type' => 'correction', 'text' => 'Competitor tag corrected: "Competitor A" → "Competitor B"', 'source' => 'Acme Demo · '.today()->format('M d').' · Agent correction', 'status' => 'pending'],
        ] as $row) {
            KnowledgeGap::query()->create($row);
        }
    }

    /**
     * Maps seeded records to their owning (central) user ids.
     *
     * @return array{company: array<string, int|null>, name: array<string, int|null>}
     */
    protected function ownerIds(): array
    {
        $central = app(DeallyTenantDatabaseManager::class)->storedCentralConnectionName();

        $emails = DB::connection($central)->table('users')->pluck('id', 'email')->all();

        $companyOwner = [
            'Acme Corp' => 'alice@example.com',
            'Globex Inc' => 'alice@example.com',
            'Wayne Enterprises' => 'alice@example.com',
            'Stark Industries' => 'charlie@example.com',
            'Initech' => 'bob@example.com',
        ];

        $nameOwner = [
            'Alice Johnson' => 'alice@example.com',
            'Bob Carter' => 'bob@example.com',
            'Charlie Lee' => 'charlie@example.com',
            'Erica Valdez' => 'erica@example.com',
            'David Chen' => 'david@example.com',
        ];

        $resolve = function (array $byEmail) use ($emails): array {
            $result = [];
            foreach ($byEmail as $key => $email) {
                $result[$key] = $emails[$email] ?? null;
            }

            return $result;
        };

        return [
            'company' => $resolve($companyOwner),
            'name' => $resolve($nameOwner),
        ];
    }
}
