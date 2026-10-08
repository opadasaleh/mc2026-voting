<?php

namespace App\Support;

use App\Models\Event;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * The QR code visitors scan to open an event's voting page (F4): posters,
 * exhibitor tables, the TV screen.
 */
final class VotingQr
{
    public static function url(Event $event): string
    {
        return config('voting.frontend_url').'/'.rawurlencode($event->slug);
    }

    /** Vector, for print. */
    public static function svg(Event $event): string
    {
        return self::render($event, QRMarkupSVG::class, ['svgAddXmlHeader' => true]);
    }

    /** 1,000+ px raster, for slides and chat apps. */
    public static function png(Event $event): string
    {
        return self::render($event, QRGdImagePNG::class, ['scale' => 30]);
    }

    /**
     * @param  class-string  $output
     * @param  array<string, mixed>  $options
     */
    private static function render(Event $event, string $output, array $options): string
    {
        return (new QRCode(new QROptions([
            // v5 only honours outputInterface with the CUSTOM type.
            'outputType' => QROutputInterface::CUSTOM,
            'outputInterface' => $output,
            'outputBase64' => false,
            // Medium error correction survives a crumpled poster or a glare on the TV.
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            ...$options,
        ])))->render(self::url($event));
    }
}
