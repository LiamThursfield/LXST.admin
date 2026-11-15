<?php

namespace App\Http\Controllers\Tenant\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Models\Catalogue\Catalogue;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * QR codes for the public catalogue pages, to print on a table, a window or a flyer: one for every
 * published catalogue, and one for each catalogue
 */
class CatalogueQrCodeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Catalogue::class);

        return $this->qrCode($request, route('catalogue.public.index'), 'catalogues');
    }

    public function show(Request $request, Catalogue $catalogue): Response
    {
        Gate::authorize('view', $catalogue);

        return $this->qrCode($request, $catalogue->publicUrl(), $catalogue->slug);
    }

    protected function qrCode(Request $request, string $url, string $filename): Response
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(512, 2), new SvgImageBackEnd)))->writeString($url);

        $response = response($svg, HttpResponse::HTTP_OK, ['Content-Type' => 'image/svg+xml']);

        if ($request->boolean('download')) {
            $response->header('Content-Disposition', 'attachment; filename="'.$filename.'-qr-code.svg"');
        }

        return $response;
    }
}
