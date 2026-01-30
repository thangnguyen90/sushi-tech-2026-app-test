<?php

namespace App\Services\Eventos\Category;

use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;
use Psr\Http\Client\ClientExceptionInterface;
use Throwable;

final class CategoryService extends EventosClient
{
    /**
     * Create a new class CategoryService instance.
     */
    public function __construct()
    {
        parent::__construct('web_api');
    }

    private function validateEventInfo(array $eventInfo): void
    {
        // Implementation from previous examples
        if (! isset($eventInfo['portal'], $eventInfo['event'], $eventInfo['moduleTicketId'])) {
            throw ValidationException::withMessages([
                'eventInfo' => 'The eventInfo array must contain "portal", "moduleTicketId", and "event" keys.',
            ]);
        }
    }

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface
     * @throws Exception|Throwable
     */
    public function getTicketByLarge($language): array
    {
        $info = $this->getPrivateInfo();
        $this->validateEventInfo($info);
        ['portal' => $portal, 'event' => $event, 'moduleTicketId' => $moduleTicketId] = $info;

        $api = $this->createApiClient()
            ->setMethod('GET')
            ->setEndpoint("/web_api/v2/ticket/$portal/$event/$moduleTicketId")
            ->setHeaders(['Language' => $language]);
        $response = $api->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true)['data']['ticket_large_categories'];
        }
        throw new \Exception('Failed to get category large by UUID. Status: ' . $response->getStatusCode());
    }

    /**
     * @throws Throwable
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function getTicketByCategory($language, $id, $type = 'Large')
    {

        $info = $this->getPrivateInfo();
            $this->validateEventInfo($info);
            ['portal' => $portal, 'event' => $event, 'moduleTicketId' => $moduleTicketId] = $info;

        $api = $this->createApiClient()
                ->setMethod('GET')
                ->setEndpoint("/web_api/v2/ticket/$portal/$event/$moduleTicketId?type=List&ticket_category_id=$id&ticket_category_type=$type")
                ->setHeaders(['Accept' => 'application/json', 'Language' => $language]);
            $response = $api->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true)['data'];
            }
        throw new \Exception('Failed to get category middle by UUID. Status: ' . $response->getStatusCode());
    }
}
