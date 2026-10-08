<?php

declare(strict_types=1);

namespace Neili\Tests\Support;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

use function Amp\ByteStream\buffer;

/**
 * In-memory HTTP client used by unit tests.
 *
 * Responses are queued via pushResponse()/pushJsonResponse() and returned in
 * FIFO order. Every received Request and its serialized body are captured so
 * tests can assert against the outgoing payload without touching the network.
 *
 * pushThrow() queues a transport-level failure: the next request() call will
 * throw the given Throwable synchronously instead of returning a Response.
 * This is used to exercise Neili's translateTransportError() path.
 *
 * This class intentionally does not implement any amphp interface: across
 * amphp/http-client builds the HttpClient symbol is inconsistent (interface
 * in some releases, class in others), so we rely on the structural typing
 * enforced by Neili\Settings::setHttpClient(object) instead.
 */
final class FakeHttpClient
{
    /** @var array<int, array{request: Request, body: string}> */
    private array $captured = [];

    /** @var array<int, array{0: int, 1: string}|\Throwable> */
    private array $responses = [];

    /**
     * Queue a raw HTTP response.
     */
    public function pushResponse(int $status, string $body): void
    {
        $this->responses[] = [$status, $body];
    }

    /**
     * Queue a JSON-encoded HTTP response.
     */
    public function pushJsonResponse(array $payload, int $status = 200): void
    {
        $this->responses[] = [$status, json_encode($payload, JSON_THROW_ON_ERROR)];
    }

    /**
     * Queue a transport-level failure. The next request() call will throw
     * the given Throwable synchronously instead of returning a Response.
     */
    public function pushThrow(\Throwable $error): void
    {
        $this->responses[] = $error;
    }

    /**
     * @return array<int, Request>
     */
    public function getRequests(): array
    {
        return array_map(static fn(array $row): Request => $row['request'], $this->captured);
    }

    /**
     * Return the most recently captured request, or null if none.
     */
    public function getLastRequest(): ?Request
    {
        if (empty($this->captured)) {
            return null;
        }

        return $this->captured[count($this->captured) - 1]['request'];
    }

    /**
     * Return the raw serialized body of the most recent request.
     */
    public function getLastRequestBody(): ?string
    {
        if (empty($this->captured)) {
            return null;
        }

        return $this->captured[count($this->captured) - 1]['body'];
    }

    /**
     * Decode the most recent request body as a JSON object.
     *
     * @return array<string, mixed>|null
     */
    public function getLastRequestPayload(): ?array
    {
        $body = $this->getLastRequestBody();
        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function request(Request $request, ?Cancellation $cancellation = null): Response
    {
        $this->captured[] = [
            'request' => $request,
            'body' => $this->extractBody($request),
        ];

        if (empty($this->responses)) {
            throw new \RuntimeException('FakeHttpClient: no queued responses remaining');
        }

        $next = array_shift($this->responses);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        [$status, $body] = $next;

        return new Response(
            '1.1',
            $status,
            null,
            [],
            new ReadableBuffer($body),
            $request,
        );
    }

    /**
     * Materialize a Request body to a string for assertion purposes.
     *
     * In amphp/http-client v5 every body (BufferedContent, Form, StreamedContent, ...)
     * implements HttpContent::getContent(), which returns a ReadableStream.
     * We buffer that stream into a string. A plain string is also tolerated
     * for forward/backward compatibility.
     */
    private function extractBody(Request $request): string
    {
        $content = $request->getBody()->getContent();

        if (is_string($content)) {
            return $content;
        }

        if ($content instanceof ReadableStream) {
            return buffer($content);
        }

        return '';
    }
}
