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

    public function test_the_brochure_paints_the_page_margins_dark(): void
    {
        $pdf = (string) $this->get(route('brochure.pdf'))->getContent();

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        $pages = 0;

        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);

            if ($content === false || ! str_contains($content, 'BT')) {
                continue;
            }

            $pages++;
            preg_match('/^0\.000 0\.000 595\.\d+ 841\.\d+ re f$/m', $content, $sheet, PREG_OFFSET_CAPTURE);
            preg_match('/^BT /m', $content, $text, PREG_OFFSET_CAPTURE);

            $this->assertNotEmpty($sheet, 'Expected a full-sheet background fill so the margins are not white.');
            // dompdf paints absolutely positioned frames last, so without a
            // negative z-index this fill buries the brochure under flat #14171c.
            $this->assertLessThan($text[0][1], $sheet[0][1], 'The page background is painted over the content.');
        }

        $this->assertSame(3, $pages);
    }
}
