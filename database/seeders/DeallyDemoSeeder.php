<?php

namespace Database\Seeders;

use Deally\Calls\Models\Call;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;
use Illuminate\Database\Seeder;

class DeallyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $oppIds = [];

        foreach ([
            ['company' => 'Acme Corp', 'contact_name' => 'Jane Doe', 'contact_title' => 'CTO', 'packages' => 'Enterprise Suite (3), Add-on X (1)', 'stage' => 'negotiation', 'value' => 48000],
            ['company' => 'Globex Inc', 'contact_name' => 'Mark Lee', 'contact_title' => 'VP Eng', 'packages' => 'Pro Plan (1)', 'stage' => 'won', 'value' => 12500],
            ['company' => 'Wayne Enterprises', 'contact_name' => 'Bruce Wayne', 'contact_title' => 'CEO', 'packages' => 'Enterprise Suite (1)', 'stage' => 'demo', 'value' => 22000],
            ['company' => 'Stark Industries', 'contact_name' => 'Pepper Potts', 'contact_title' => 'COO', 'packages' => 'Pro Plan (2)', 'stage' => 'discovery', 'value' => 18000],
        ] as $row) {
            $opp = Opportunity::query()->create($row);
            $oppIds[$opp->company] = $opp->getKey();
        }

        foreach ([
            ['name' => 'Demo & Discovery', 'company' => 'Acme Corp', 'duration' => '24m', 'sentiment' => 'positive', 'contact_name' => 'Jane Doe', 'contact_role' => 'CTO', 'date' => today(), 'opportunity_id' => $oppIds['Acme Corp'] ?? null, 'summary' => 'Jane confirmed Acme is currently using Cisco but frustrated with slow support and rising costs. They need native Slack integration and SSO. Budget approved. Agreed to send an Enterprise proposal with custom onboarding.', 'notes' => 'Customer worried about migration timeline.'],
            ['name' => 'Discovery', 'company' => 'Globex Inc', 'duration' => '18m', 'sentiment' => 'neutral', 'contact_name' => 'Mark Lee', 'contact_role' => 'VP Eng', 'date' => now()->subDays(4), 'opportunity_id' => $oppIds['Globex Inc'] ?? null],
            ['name' => 'Intro Call', 'company' => 'Initech', 'duration' => '12m', 'sentiment' => 'negative', 'contact_name' => 'Sam King', 'contact_role' => 'IT Lead', 'date' => now()->subDays(6)],
        ] as $row) {
            $call = Call::query()->create(['sentiment' => $row['sentiment'] ?? 'neutral'] + $row);

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
            ['title' => 'Review Call — Acme Corp', 'linked_company' => 'Acme Corp', 'due_at' => today(), 'status' => 'todo'],
            ['title' => 'Send pricing to Globex', 'linked_company' => 'Globex Inc', 'due_at' => today(), 'status' => 'todo'],
            ['title' => 'Prep battle card for Acme', 'linked_company' => 'Acme Corp', 'due_at' => today()->addDay(), 'status' => 'todo'],
            ['title' => 'Schedule QBR with Wayne', 'linked_company' => 'Wayne Enterprises', 'due_at' => today()->addDays(2), 'status' => 'todo'],
            ['title' => 'Follow up with Initech', 'linked_company' => 'Initech', 'due_at' => now()->subDays(2), 'status' => 'todo'],
            ['title' => 'Send spec sheet to Acme', 'linked_company' => 'Acme Corp', 'due_at' => now()->subDays(3), 'status' => 'todo'],
            ['title' => 'Review proposal v2', 'linked_company' => 'Acme Corp', 'due_at' => now()->subDay(), 'status' => 'closed'],
            ['title' => 'Send call recap', 'linked_company' => 'Globex Inc', 'due_at' => now()->subDays(2), 'status' => 'closed'],
        ] as $row) {
            Task::query()->create($row);
        }

        foreach ([
            ['name' => 'Acme Enterprise v2', 'company' => 'Acme Corp', 'value' => 81000, 'status' => 'viewed', 'package' => 'Enterprise Suite — 500 users', 'quote' => '"As you mentioned during our call, ensuring stability under load is critical."', 'line_items' => [['item' => 'Enterprise Suite — 500 users', 'amount' => '$7,500/mo'], ['item' => 'Custom Onboarding', 'amount' => 'Included']]],
            ['name' => 'Globex Pro Plan', 'company' => 'Globex Inc', 'value' => 12500, 'status' => 'approved', 'package' => 'Pro Plan — 200 users'],
            ['name' => 'Initech Starter', 'company' => 'Initech', 'value' => 6200, 'status' => 'rejected', 'package' => 'Starter — 50 users'],
        ] as $row) {
            Proposal::query()->create($row);
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
}
