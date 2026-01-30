<?php

namespace App\Services\Eventos\Image;

use App\Services\Eventos\Api\ApiResource;
use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Psr7\Utils;

final class PublicImageService extends EventosClient
{


    /**
     * Uploads an image.
     *
     * @throws Exception
     */
    public function upload(string $filePath, string $fieldName = 'image'): array
    {
        if (! file_exists($filePath)) {
            throw new Exception("File not found at path: $filePath");
        }

        $client = $this->createApiClient();
        $client->setMethod('POST')->setEndpoint('/api/v1/image');

        $multipartData = [
            [
                'name' => $fieldName,
                'contents' => Utils::tryFopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ];
        $client->setBody(['multipart' => $multipartData]);

        $response = $client->send();
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true) ?? [];
        }

        throw new Exception('Failed to upload image. Status: '.$response->getStatusCode());
    }

}
