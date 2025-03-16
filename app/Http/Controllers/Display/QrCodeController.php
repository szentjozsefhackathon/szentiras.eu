<?php
/**

 */

namespace SzentirasHu\Http\Controllers\Display;

use Config;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
// use Endroid\QrCode\Label\Label;
// use Endroid\QrCode\Logo\Logo;
// use Endroid\QrCode\Label\Margin\Margin;
use Imagine\Gd\Font;
use Imagine;
// use Endroid\QrCode\Color\Color;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use Request;
use Response;
use SzentirasHu\Http\Controllers\Controller;
use View;

class QrCodeController extends Controller
{

    public function index($url)
    {
        $size = 150;
        $qrCode = new QrCode(
            data: $url,
            size: $size,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            margin: 5
        );

        $logoText = Config::get('settings.brand.domain');
        $margin = 10;
        $palette = new RGB();
        $font = new Font(
            base_path() . "/resources/assets/fonts/DejaVuSans.ttf",
            9,
            $palette->color('#000000')
        );
        $textBox = $font->box($logoText)->increase($margin);
        $logo = Imagine::create($textBox, $palette->color('#ffffff'));
        $logo->draw()->text(
            $logoText,
            $font,
            new Point($margin / 2, $margin / 2)
        );
        $image = Imagine::load((new PngWriter())->write($qrCode)->getString());
        $image->paste(
            $logo,
            new Point(
                ($size - $textBox->getWidth()) / 2,
                ($size - $textBox->getHeight()) / 2
            )
        );

        return Response::make($image, 200, array('content-type' => 'image/png'));
    }

    public function dialog($url)
    {
        return View::make('textDisplay.qrDialog')->with(['url' => $url]);
    }

}