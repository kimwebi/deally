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
}
