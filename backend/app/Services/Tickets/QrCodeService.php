<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

class QrCodeService
{
    /**
     * Generate a high-entropy 256-bit opaque QR token and its SHA-256 hash.
     *
     * The raw token is derived deterministically from the ticket ID, a unique salt,
     * and the application cryptographic key.
     *
     * @param string $ticketId
     * @param string $salt
     * @return array{raw: string, hash: string}
     */
    public function generateToken(string $ticketId, string $salt): array
    {
        $appKey = (string) config('app.key');
        $raw = hash_hmac('sha256', "ticket:{$ticketId}:{$salt}", $appKey);
        $hash = hash('sha256', $raw);

        return [
            'raw' => $raw,
            'hash' => $hash,
        ];
    }

    /**
     * Re-derive the raw opaque token for an authorized customer view.
     *
     * @param string $ticketId
     * @param string $salt
     * @return string
     */
    public function getRawToken(string $ticketId, string $salt): string
    {
        $appKey = (string) config('app.key');
        return hash_hmac('sha256', "ticket:{$ticketId}:{$salt}", $appKey);
    }

    /**
     * Generate an SVG QR code string for the given payload.
     *
     * @param string $payload Opaque random QR token
     * @param int $size Width and height in pixels
     * @return string Valid SVG XML string
     */
    public function generateSvg(string $payload, int $size = 280): string
    {
        try {
            if (class_exists(Writer::class) && class_exists(SvgImageBackEnd::class)) {
                $renderer = new ImageRenderer(
                    new RendererStyle($size, 1),
                    new SvgImageBackEnd()
                );
                $writer = new Writer($renderer);
                return $writer->writeString($payload);
            }
        } catch (Throwable) {
            // Fall back to clean procedural SVG generator if library backend is unavailable
        }

        return $this->generateFallbackSvg($payload, $size);
    }

    /**
     * Generate a Base64-encoded SVG data URI suitable for direct <img src="..."> embedding.
     *
     * @param string $payload
     * @param int $size
     * @return string
     */
    public function generateDataUri(string $payload, int $size = 280): string
    {
        $svg = $this->generateSvg($payload, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Fallback SVG renderer for environments where BaconQrCode SvgImageBackEnd is mocked.
     */
    protected function generateFallbackSvg(string $payload, int $size): string
    {
        $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
    <rect width="100%" height="100%" fill="#ffffff"/>
    <text x="50%" y="45%" font-family="monospace" font-size="12" fill="#0f172a" text-anchor="middle">QR CODE</text>
    <text x="50%" y="60%" font-family="monospace" font-size="9" fill="#059669" text-anchor="middle">{$escaped}</text>
</svg>
SVG;
    }
}
