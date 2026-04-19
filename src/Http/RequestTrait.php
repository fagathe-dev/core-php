<?php

namespace Fagathe\CorePhp\Http;

use Symfony\Component\HttpFoundation\Request;

trait RequestTrait
{
    /**
     * Retrieves the `Request` object from global variables.
     *
     * @return Request The Request object containing request information.
     *
     * @example
     * ```php
     * $request = $this->getRequest();
     * echo $request->getMethod(); // "GET"
     * ```
     */
    public function getRequest(): Request
    {
        return Request::createFromGlobals();
    }

    /**
     * Gets the HTTP method of the request (GET, POST, etc.).
     *
     * @return string The HTTP method of the request.
     *
     * @example
     * ```php
     * echo $this->getRequestMethod(); // "GET"
     * ```
     */
    public function getRequestMethod(): string
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * Retrieves the full request URI.
     *
     * @return string The URI requested by the client.
     *
     * @example
     * ```php
     * echo $this->getRequestUri(); // "/page?param=1"
     * ```
     */
    public function getRequestUri(): string
    {
        return $this->getRequest()->getRequestUri();
    }

    /**
     * Gets only the request path without query parameters.
     *
     * @return string The request path.
     *
     * @example
     * ```php
     * echo $this->getRequestPath(); // "/page"
     * ```
     */
    public function getRequestPath(): string
    {
        return $this->getRequest()->getPathInfo();
    }

    /**
     * Retrieves the request origin (scheme + host).
     *
     * @return string The request origin (e.g., "https://example.com").
     *
     * @example
     * ```php
     * echo $this->getOrigin(); // "https://example.com"
     * ```
     */
    public function getOrigin(): string
    {
        return $this->getRequest()->getSchemeAndHttpHost();
    }

    /**
     * Retrieves the request referer (initiator URL) or CLI indicator.
     * This represents the page that initiated the request.
     *
     * @return string|null The referer URL, "CLI" for command line, or null if not available.
     *
     * @example
     * ```php
     * echo $this->getRequestReferer(); // "/" or "CLI" or null
     * ```
     */
    public function getRequestReferer(): ?string
    {
        // Si c'est une commande CLI
        if (php_sapi_name() === 'cli') {
            return 'CLI';
        }

        // Récupérer le referer HTTP
        $referer = $this->getRequest()->headers->get('referer');

        if ($referer) {
            // Extraire uniquement le path depuis l'URL complète du referer
            $parsedUrl = parse_url($referer);
            return $parsedUrl['path'] ?? $referer;
        }

        return null;
    }

    /**
     * Gets the full request URL (scheme, host, and URI).
     *
     * @return string The full request URL.
     *
     * @example
     * ```php
     * echo $this->getRequestFullUrl(); // "https://example.com/page?param=1"
     * ```
     */
    private function getRequestFullUrl(): string
    {
        return $this->getRequest()->getSchemeAndHttpHost() . $this->getRequest()->getRequestUri();
    }

    /**
     * Retrieves the canonical URL of the request (scheme, host, and path).
     *
     * @return string The canonical request URL.
     *
     * @example
     * ```php
     * echo $this->getRequestCanonicalUrl(); // "https://example.com/page"
     * ```
     */
    public function getRequestCanonicalUrl(): string
    {
        return $this->getRequest()->getSchemeAndHttpHost() . $this->getRequest()->getPathInfo();
    }

    public function getUserAgent(): string
    {
        return $this->getRequest()->headers->get('User-Agent', 'Unknown');
    }
}