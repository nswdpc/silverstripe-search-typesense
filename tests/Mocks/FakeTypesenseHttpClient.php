<?php

namespace NSWDPC\Search\Typesense\Tests\Mocks;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A minimal PSR-18 HTTP client used in place of a real Typesense server.
 *
 * Tests queue up the response(s) they expect the Typesense PHP SDK to receive
 * (in the order the SDK will make requests) via queueJson()/queueRaw(), then
 * inspect getRequests() to assert what the module actually sent.
 *
 * A unique instance id is included so two instances never collide in
 * ClientManager's static client cache (which keys clients by a hash of the
 * client config array, and json_encode() of a plain object only includes its
 * public properties).
 */
class FakeTypesenseHttpClient implements ClientInterface
{
    public string $instanceId;

    /**
     * @var ResponseInterface[]
     */
    protected array $responses = [];

    /**
     * @var RequestInterface[]
     */
    protected array $requests = [];

    public function __construct()
    {
        $this->instanceId = uniqid('fake-typesense-http-client-', true);
    }

    /**
     * Queue a JSON response
     */
    public function queueJson(int $status, array $body): static
    {
        $this->responses[] = new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body)
        );
        return $this;
    }

    /**
     * Queue a raw-body response (e.g. the JSONL bodies used by the documents import endpoint)
     */
    public function queueRaw(int $status, string $body): static
    {
        $this->responses[] = new Response($status, [], $body);
        return $this;
    }

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);
        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException(
                'FakeTypesenseHttpClient received a request with no response queued: '
                . $request->getMethod() . ' ' . (string) $request->getUri()
            );
        }

        return $response;
    }

    /**
     * @return RequestInterface[]
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getLastRequest(): ?RequestInterface
    {
        return $this->requests === [] ? null : $this->requests[count($this->requests) - 1];
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }
}
