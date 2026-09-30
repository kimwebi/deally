<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_terms_and_privacy_pages_are_publicly_accessible(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Wyzone Labs');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Wyzone Labs')
            ->assertSee('AI processing');
    }

    public function test_legal_pages_link_to_sign_in_and_to_each_other(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee(route('login'))
            ->assertSee(route('legal.privacy'));

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee(route('login'))
            ->assertSee(route('legal.terms'));
    }

    public function test_legal_pages_render_copyright_footer(): void
    {
        $footer = '© '.date('Y').' DeAlly · AI-powered sales enablement · Wyzone Labs';

        $this->get(route('legal.terms'))->assertOk()->assertSee($footer);
        $this->get(route('legal.privacy'))->assertOk()->assertSee($footer);
    }

    public function test_sign_in_page_links_to_terms_and_privacy(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'));
    }
}
