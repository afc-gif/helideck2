<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudinaryEvidenceStorage
{
    /** @param array<string, string|int|bool> $parameters */
    public function uploadSignature(array $parameters): string
    {
        $secret = $this->requiredConfig('api_secret');
        ksort($parameters);
        $parts = [];
        foreach ($parameters as $key => $value) {
            $parts[] = $key.'='.$this->signatureValue($value);
        }

        return sha1(implode('&', $parts).$secret);
    }

    /** @return array<string, mixed> */
    public function verifyAuthenticatedImage(string $publicId): array
    {
        $cloudName = $this->requiredConfig('cloud_name');
        $response = Http::withBasicAuth($this->requiredConfig('api_key'), $this->requiredConfig('api_secret'))
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.cloudinary.com/v1_1/'.rawurlencode($cloudName).'/resources/image/authenticated/'.str_replace('%2F', '/', rawurlencode($publicId)));

        if (! $response->successful()) {
            throw new RuntimeException('Cloudinary could not verify the uploaded image.');
        }

        $asset = $response->json();
        if (! is_array($asset) || ($asset['type'] ?? null) !== 'authenticated' || ($asset['resource_type'] ?? null) !== 'image') {
            throw new RuntimeException('Cloudinary returned an image with an unexpected access type.');
        }

        return $asset;
    }

    public function signedDeliveryUrl(string $publicId, string $format, int $version): string
    {
        $directives = 'v'.$version.'/'.$publicId.'.'.$format;
        $digest = sha1($directives.$this->requiredConfig('api_secret'), true);
        $signature = substr(rtrim(strtr(base64_encode($digest), '+/', '-_'), '='), 0, 8);
        $path = implode('/', array_map('rawurlencode', explode('/', $publicId)));

        return 'https://res.cloudinary.com/'.rawurlencode($this->requiredConfig('cloud_name'))
            .'/image/authenticated/s--'.$signature.'--/v'.$version.'/'.$path.'.'.rawurlencode($format);
    }

    public function cloudName(): string
    {
        return $this->requiredConfig('cloud_name');
    }

    public function apiKey(): string
    {
        return $this->requiredConfig('api_key');
    }

    public function folder(): string
    {
        return trim((string) config('services.cloudinary.folder', 'helideck'), '/');
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config('services.cloudinary.'.$key, ''));
        if ($value === '') {
            throw new RuntimeException('Cloudinary is not configured on the backend.');
        }

        return $value;
    }

    private function signatureValue(string|int|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
