<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Models\Dj\Gig;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * A QR code for the gig's request link, to print or put on a screen
 */
class GigQrCodeController extends Controller
{
    public function show(Gig $gig): Response
    {
        Gate::authorize('view', $gig);

        $url = $gig->requestUrl();

        abort_if($url === null, HttpResponse::HTTP_NOT_FOUND);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(512, 2), new SvgImageBackEnd)))->writeString($url);

        $response = response($svg, HttpResponse::HTTP_OK, ['Content-Type' => 'image/svg+xml']);

        if (request()->boolean('download')) {
            $response->header('Content-Disposition', 'attachment; filename="'.Str::slug($gig->name).'-requests.svg"');
        }

        return $response;
    }
}
