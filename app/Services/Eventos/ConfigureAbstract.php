<?php

namespace App\Services\Eventos;

use App\Services\Eventos\Api\PrivateLoginClient;
use App\Services\Eventos\Api\PublicLoginClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Abstract configuration for the Eventos API.
 *
 * This class provides the base configuration and authentication logic
 * for both public and private Eventos API endpoints.
 *
 * @author  Nhathaminh
 *
 * @since   2024-11-06
 */
abstract class ConfigureAbstract
{
    // region Properties
    protected string $publicUrl;

    protected string $publicSecret;

    protected ?string $publicToken = null;

    protected string $baseUrl;

    protected string $consoleAdmin;

    protected string $consoleAdminSecret;

    protected ?int $client;

    protected ?int $portal;

    protected ?int $event;

    protected ?int $moduleTicketId;

    protected ?int $module = null;

    protected mixed $cookie = null;

    protected bool $isPublic = false;

    protected bool $isPrivate = false;

    public string $status = 'Public';
    // endregion

    public function __construct(string $status)
    {
        if ($status === 'Private') {
            $this->_setupPrivate();
        }else if ($status === 'web_api'){
            $this->_setupWebApi();
        }
        else {
            $this->_setupPublic();
        }
        $this->status = $status;
        $this->_setPortal(config('eventos.portal'));
        $this->_setEvent(config('eventos.event'));
        $this->_setModuleTicketId(config('eventos.module_ticket_id'));
        $this->_setClient(config('eventos.client'));
    }

    // region Public Methods

    /**
     * @throws Exception
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists($this, $name)) {
            return $this->$name(...$arguments);
        }
        try {
            return $this->_getConfig($name, $arguments);
        } catch (GuzzleException|ClientExceptionInterface $e) {
            throw new Exception("Error in method '{$name}': ".$e->getMessage(), 0, $e);
        }
    }
    // endregion

    // region Protected Getters & Setters
    protected function _getPortal(): ?int
    {
        return $this->portal;
    }

    protected function _setPortal(?int $portal): ConfigureAbstract
    {
        $this->portal = $portal;

        return $this;
    }

    protected function _getEvent(): ?int
    {
        return $this->event;
    }

    protected function _getModuleTicketId(): ?int
    {
        return $this->moduleTicketId;
    }

    protected function _setEvent(?int $event): ConfigureAbstract
    {
        $this->event = $event;

        return $this;
    }

    protected function _setModuleTicketId(?int $moduleTicketId): ConfigureAbstract
    {
        $this->moduleTicketId = $moduleTicketId;

        return $this;
    }

    protected function _getModule(): ?int
    {
        return $this->module;
    }

    protected function _setModule(?int $module): ConfigureAbstract
    {
        $this->module = $module;

        return $this;
    }

    protected function _getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }

    protected function _setPublicUrl(?string $url): ConfigureAbstract
    {
        $this->publicUrl = $url;

        return $this;
    }

    protected function _getPublicSecret(): ?string
    {
        return $this->publicSecret;
    }

    protected function _setPublicSecret(?string $secret): ConfigureAbstract
    {
        $this->publicSecret = $secret;

        return $this;
    }

    /**
     * @throws GuzzleException
     */
    protected function _getPublicToken($force = false): ?string
    {
        if ($this->publicToken === null) {
            if($force) {
                Cache::forget('public_token');
            }
            $cachedToken = Cache::get('public_token');
            if ($cachedToken) {
                $this->publicToken = $cachedToken;
            } else {
                // call the PublicLoginClient to get a new token
                $loginService = App::make(PublicLoginClient::class, [
                    'publicUrl' => $this->_getPublicUrl(),
                    'secret' => $this->_getPublicSecret(),
                ]);
                $this->publicToken = $loginService->__invoke();
            }
        }

        return $this->publicToken;
    }

    protected function _getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    protected function _setBaseUrl(?string $url): ConfigureAbstract
    {
        $this->baseUrl = $url;

        return $this;
    }

    protected function _getConsoleAdmin(): ?string
    {
        return $this->consoleAdmin;
    }

    protected function _setConsoleAdmin(?string $admin): ConfigureAbstract
    {
        $this->consoleAdmin = $admin;

        return $this;

    }

    protected function _getConsoleAdminSecret(): ?string
    {
        return $this->consoleAdminSecret;
    }

    protected function _setConsoleAdminSecret(?string $secret): ConfigureAbstract
    {
        $this->consoleAdminSecret = $secret;

        return $this;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     */
    protected function _getCookies($force = false): mixed
    {
        if ($this->cookie === null) {
            if($force) {
                Cache::forget('admin-cookies');
            }
            $cachedCookie = Cache::get('admin-cookies');
            if ($cachedCookie) {
                $this->cookie = $cachedCookie;
            } else {
                // call the PrivateLoginClient to get a new cookie
                $loginService = App::make(PrivateLoginClient::class, [
                    'baseUrl' => $this->_getBaseUrl(),
                    'username' => $this->_getConsoleAdmin(),
                    'password' => $this->_getConsoleAdminSecret(),
                ]);
                $this->cookie = $loginService->__invoke();
            }
        }

        return $this->cookie;
    }

    protected function _getClient(): ?int
    {
        return $this->client;
    }

    protected function _setClient(?int $client): ConfigureAbstract
    {
        $this->client = $client;

        return $this;
    }
    // endregion

    // region Protected Helper Methods
    protected function _setupPublic(): ConfigureAbstract
    {
        $this->isPublic = true;
        $this->isPrivate = false;
        $this->_setPublicUrl(config('eventos.public.open_api'));
        $this->_setPublicSecret(config('eventos.public.key'));

        return $this;
    }

    protected function _setupPrivate(): ConfigureAbstract
    {
        $this->isPrivate = true;
        $this->isPublic = false;
        $this->_setBaseUrl(config('eventos.private.base_url'));
        $this->_setConsoleAdmin(config('eventos.private.username'));
        $this->_setConsoleAdminSecret(config('eventos.private.password'));

        return $this;
    }

    protected function _setupWebApi(): ConfigureAbstract
    {
        $this->isPrivate = false;
        $this->isPublic = false;
        $this->_setBaseUrl(config('eventos.web_api.base_url'));
        return $this;
    }

    protected function _getBaseUrlWebApi(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    protected function _getConfig(string $option, mixed $arguments = []): mixed
    {
        return match ($option) {
            'baseUrl' => $this->_getBaseUrl(),
            'publicUrl' => $this->_getPublicUrl(),
            'publicSecret' => $this->_getPublicSecret(),
            'consoleAdmin' => $this->_getConsoleAdmin(),
            'consoleAdminSecret' => $this->_getConsoleAdminSecret(),
            'publicToken' => $this->_getPublicToken(),
            'cookies' => $this->_getCookies(),
            'isPublic' => $this->isPublic,
            'isPrivate' => $this->isPrivate,
            'portal' => $this->_getPortal(),
            'event' => $this->_getEvent(),
            'moduleTicketId' => $this->_getModuleTicketId(),
            'module' => $this->_getModule(),
            'client' => $this->_getClient(),
            'eventInfo' => $this->_getEventInfo(),
            'infoPublic' => $this->_getInfoPublic(),
            'infoPrivate' => $this->_getInfoPrivate(),
            'setModule' => $this->_setModule($arguments[0]),
            default => throw new Exception("Option '{$option}' not found"),
        };
    }

    protected function _getEventInfo(): array
    {
        return [
            'portal' => $this->_getPortal(),
            'event' => $this->_getEvent(),
            'module' => $this->_getModule(),
        ];
    }

    /**
     * @throws GuzzleException
     */
    protected function _getInfoPublic(): array
    {
        return [
            'publicUrl' => $this->_getPublicUrl(),
            'publicSecret' => $this->_getPublicSecret(),
            'publicToken' => $this->_getPublicToken(),
        ];
    }

    /**
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     */
    protected function _getInfoPrivate(): array
    {
        return [
            'baseUrl' => $this->_getBaseUrl(),
            'client' => $this->_getClient(),
            'portal' => $this->_getPortal(),
            'event' => $this->_getEvent(),
            'moduleTicketId' => $this->_getModuleTicketId(),
            'cookies' => $this->_getCookies(),
        ];
    }
    // endregion
}
