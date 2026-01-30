<?php

namespace App\Services\Eventos\Module;

use App\Services\Eventos\Api\ApiResource;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class ModuleService
{
    private string $baseUrl;

    private array $cookies;

    /** @var array|null In-memory cache for module contents to avoid redundant API calls. */
    private ?array $moduleContents = null;

    /**
     * ModuleService constructor.
     */
    public function __construct(string $baseUrl, array $cookies)
    {
        $this->baseUrl = $baseUrl;
        $this->cookies = $cookies;
    }

    /**
     * Get a specific field (alias, title, tab_name) from modules matching a code.
     *
     * @throws ValidationException|Exception
     */
    public function getFieldByCode(array $eventInfo, string $code, string $field, string $lang = 'jpn'): array
    {
        // Lazy-load contents only when needed
        if ($this->moduleContents === null) {
            $this->moduleContents = $this->fetchContents($eventInfo);
        }

        $menuList = Arr::get($this->moduleContents, 'production.menu.list', []);

        $filteredData = array_filter($menuList, function ($item) use ($lang, $code) {
            return isset($item[$lang]) && $item[$lang]['code'] === $code;
        });

        return match ($field) {
            'alias' => $this->_extractField($filteredData, $lang, 'alias'),
            'title' => $this->_extractField($filteredData, $lang, 'settings.title'),
            'tab_name' => $this->_extractField($filteredData, $lang, 'settings.tab_name'),
            default => throw new Exception("Field '$field' not found or is not supported."),
        };
    }

    /**
     * Fetches the raw contents from the API.
     * This method can be made public if needed elsewhere.
     *
     * @throws ValidationException|Exception
     */
    private function fetchContents(array $eventInfo): array
    {
        $this->_validateEventInfo($eventInfo);
        ['portal' => $portal, 'event' => $event] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('GET');
        $client->setEndpoint("/console/api/content/$portal/$event");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to get module contents. Status: '.$response->getStatusCode());
    }

    /**
     * Helper to extract a specific field from the filtered data.
     *
     * @param  array  $data  The filtered data array.
     * @param  string  $lang  The language key.
     * @param  string  $fieldKey  The key to extract (e.g., 'alias' or 'settings.title').
     */
    private function _extractField(array $data, string $lang, string $fieldKey): array
    {
        $results = [];
        foreach ($data as $item) {
            // Use Arr::get for safe access to nested array keys
            $value = Arr::get($item, "$lang.$fieldKey");
            if ($value !== null) {
                $results[Arr::get($item, "$lang.content_id")] = $value;
            }
        }

        return $results;
    }

    private function _createApiClient(): ApiResource
    {
        // Implementation from previous examples
        $client = new class($this->baseUrl) extends ApiResource {};
        $client->setHeaders(['Accept' => 'application/json']);
        $client->setCookies($this->cookies);

        return $client;
    }

    private function _validateEventInfo(array $eventInfo): void
    {
        // Implementation from previous examples
        if (! isset($eventInfo['portal'], $eventInfo['event'])) {
            throw ValidationException::withMessages([
                'eventInfo' => 'The eventInfo array must contain "portal" and "event" keys.',
            ]);
        }
    }
}
