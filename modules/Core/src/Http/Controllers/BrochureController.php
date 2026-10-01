<?php

namespace Deally\Core\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;

class BrochureController extends Controller
{
    /**
     * Stream the marketing brochure as a downloadable PDF.
     *
     * Rendered with the system's own design tokens (dark surfaces, IBM Plex
     * fonts, the blue primary) via dompdf, so the export matches the product
     * rather than a generic template.
     */
    public function __invoke()
    {
        $pdf = Pdf::loadView('core::pages.brochure')
            ->setPaper('a4', 'portrait')
            ->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="DeAlly-Brochure.pdf"',
        ]);
    }
}
