<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Conversions API (CAPI) — server-side event tracking.
 *
 * Sends events to Meta's Graph API to complement the client-side Pixel.
 * Uses the same eventID as the browser Pixel for automatic deduplication.
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api
 */
class MetaConversionsService
{
    private const GRAPH_API_VERSION = 'v21.0';

    /**
     * Send an event to Meta Conversions API.
     *
     * Fires asynchronously (non-blocking) so it doesn't slow down the request.
     * Failures are logged but never bubble up to the user.
     */
    public static function sendEvent(
        string $eventName,
        string $eventId,
        array $userData = [],
        array $customData = [],
        ?string $sourceUrl = null,
    ): void {
        try {
            $pixelId = Setting::getValue('meta_pixel_id', '');
            $enabled = Setting::getValue('meta_pixel_enabled', '0') === '1';
            $encryptedToken = Setting::getValue('meta_capi_token', '');

            if (!$enabled || !$pixelId || !$encryptedToken) {
                return;
            }

            $accessToken = Crypt::decryptString($encryptedToken);
        } catch (\Exception $e) {
            Log::warning('Meta CAPI: failed to read config', ['error' => $e->getMessage()]);
            return;
        }

        // Build user_data with hashing (Meta requires SHA-256 for PII)
        $hashedUserData = self::hashUserData($userData);

        $eventData = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => $hashedUserData,
        ];

        if ($sourceUrl) {
            $eventData['event_source_url'] = $sourceUrl;
        }

        if (!empty($customData)) {
            $eventData['custom_data'] = $customData;
        }

        $url = 'https://graph.facebook.com/' . self::GRAPH_API_VERSION . '/' . $pixelId . '/events';

        // Synchronous HTTP with short timeout — consider queuing for high-traffic sites
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->asJson()
                ->post($url, [
                    'data' => [$eventData],
                    'access_token' => $accessToken,
                ]);

            if (!$response->successful()) {
                Log::warning('Meta CAPI: API error', [
                    'event' => $eventName,
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Meta CAPI: request failed', [
                'event' => $eventName,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hash PII fields per Meta's requirements (lowercase + SHA-256).
     *
     * @see https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters
     */
    private static function hashUserData(array $raw): array
    {
        $hashed = [];

        if (!empty($raw['email'])) {
            $emailHash = hash('sha256', strtolower(trim($raw['email'])));
            $hashed['em'] = [$emailHash];
            // Use hashed email as external_id for improved match quality
            $hashed['external_id'] = [$emailHash];
        }

        if (!empty($raw['ip'])) {
            $hashed['client_ip_address'] = $raw['ip'];
        }

        if (!empty($raw['user_agent'])) {
            $hashed['client_user_agent'] = $raw['user_agent'];
        }

        if (!empty($raw['fbc'])) {
            $hashed['fbc'] = $raw['fbc'];
        }

        if (!empty($raw['fbp'])) {
            $hashed['fbp'] = $raw['fbp'];
        }

        return $hashed;
    }

    /**
     * Extract Meta click/browser IDs from cookies for better attribution.
     */
    public static function extractMetaCookies(?\Illuminate\Http\Request $request = null): array
    {
        $request = $request ?? request();
        return array_filter([
            'fbc' => $request->cookie('_fbc'),
            'fbp' => $request->cookie('_fbp'),
        ]);
    }

    /**
     * Build user data array from a request + optional email.
     */
    public static function buildUserData(?\Illuminate\Http\Request $request = null, ?string $email = null): array
    {
        $request = $request ?? request();

        return array_filter(array_merge([
            'email' => $email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], self::extractMetaCookies($request)));
    }
}
