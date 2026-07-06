<?php

namespace App\Services;

use App\Models\Transcript;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders an issued transcript to PDF bytes: the Blade template with an embedded
 * SVG QR pointing at the public verify URL, laid out by mpdf with repeating
 * table headers and a per-page footer.
 */
class TranscriptPdfRenderer
{
    public function render(Transcript $transcript): string
    {
        $verifyUrl = route('transcripts.verify', $transcript->transcript_number);

        // bacon/bacon-qr-code is already installed (Fortify 2FA dependency); render
        // the SVG directly, then drop the XML prolog mpdf cannot parse inline.
        $qrSvg = (new Writer(new ImageRenderer(
            new RendererStyle(120, 0),
            new SvgImageBackEnd,
        )))->writeString($verifyUrl);
        $qrSvg = preg_replace('/^<\?xml.*?\?>\s*/s', '', $qrSvg);

        $html = View::make('pdf.transcript', [
            'transcript' => $transcript,
            'snapshot' => $transcript->snapshot,
            'verifyUrl' => $verifyUrl,
            'qrSvg' => $qrSvg,
        ])->render();

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:8px;color:#999;">'
            .e($transcript->transcript_number).' · Page {PAGENO}/{nbpg}</div>'
        );

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
