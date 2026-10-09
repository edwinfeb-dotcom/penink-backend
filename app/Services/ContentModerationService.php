<?php

namespace App\Services;

class ContentModerationService
{
    /**
     * Cek apakah URL mengandung konten terlarang.
     * Return array ['blocked' => bool, 'reason' => string|null]
     */
    public static function check(string $url): array
    {
        $keywords = config('blocked_content.keywords', []);
        $domains = config('blocked_content.domains', []);

        $urlLower = strtolower($url);

        // 1. Cek keyword di seluruh URL
        foreach ($keywords as $keyword) {
            if (str_contains($urlLower, $keyword)) {
                return [
                    'blocked' => true,
                    'reason' => 'keyword:' . $keyword,
                    'message' => config('blocked_content.message'),
                ];
            }
        }

        // 2. Cek domain blacklist
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            $host = strtolower($host);

            // Hapus 'www.' di depan
            $host = preg_replace('/^www\./', '', $host);

            foreach ($domains as $blockedDomain) {
                $blockedDomain = strtolower($blockedDomain);

                if ($host === $blockedDomain || str_ends_with($host, '.' . $blockedDomain)) {
                    return [
                        'blocked' => true,
                        'reason' => 'domain:' . $blockedDomain,
                        'message' => config('blocked_content.message'),
                    ];
                }
            }
        }

        return [
            'blocked' => false,
            'reason' => null,
            'message' => null,
        ];
    }
}