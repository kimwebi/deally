<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrochureTest extends TestCase
{
    public function test_the_brochure_downloads_as_a_pdf(): void
    {
        $response = $this->get(route('brochure.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_the_brochure_embeds_the_system_font(): void
    {
        $pdf = (string) $this->get(route('brochure.pdf'))->getContent();

        $this->assertStringContainsString('IBMPlexSans', $pdf);
        $this->assertStringContainsString('IBMPlexMono', $pdf);
    }

    public function test_the_brochure_renders_as_three_pages(): void
    {
        $pdf = (string) $this->get(route('brochure.pdf'))->getContent();

        // Guards against a page container that overshoots the content box and
        // silently spills every page onto a blank extra sheet.
        preg_match_all('/\/Type\s*\/Page(?![s])/', $pdf, $matches);

        $this->assertCount(3, $matches[0]);
    }
}
