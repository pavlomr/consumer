<?php
/*
 * Copyright (c) 2023 Pavlo Marenyuk <pavlomr@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace pavlomr\Service\Consumer;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils as GuzzlePSR7Utils;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\UriTemplate\UriTemplate;
use GuzzleHttp\Utils as GuzzleUtils;
use JsonException;
use pavlomr\Normalizer\NormalizerAwareInterface;
use pavlomr\Normalizer\NormalizerAwareTrait;
use pavlomr\Service\SingletonTrait;
use pavlomr\Service\Tools\CompatibilityNormalizer;
use pavlomr\Service\UserAgentTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

use function json_decode;

abstract class GuzzleDecorator implements DecoratorInterface, LoggerAwareInterface, NormalizerAwareInterface
{
    use LoggerAwareTrait;
    use SingletonTrait;
    use UserAgentTrait;
    use NormalizerAwareTrait;

    protected const         HTTP_VERSION = 1.1;
    protected const         ACCEPT_CONTENT = 'application/json';

    protected string $method = 'post';
    protected string $base;
    protected string $path;
    /** @var array<int|string, string> */
    private array           $auth    = [];
    private ClientInterface $client;
    private array           $headers = [];
    /** @var array<string, bool|int|string|array> */
    private array $options = [];

    /**
     * @param array<callable> $handlers
     */
    protected function __construct(array $handlers = [])
    {
        $config = $this->createConfig();
        foreach ($handlers as $handler) {
            $config['handler']->push($handler);
        }

        $this
            ->setClient(new Client($config))
            ->setNormalizer($this->normalizer ?? new CompatibilityNormalizer())
            ->setLogger($this->logger ?? new NullLogger())
        ;
    }

    /**
     * @return array<int|string, string>
     */
    public function getAuth(): array
    {
        return $this->auth;
    }

    /**
     * @param array<string> $auth
     *
     * @return $this
     */
    public function setAuth($auth): static
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * @return string
     */
    public function getBase(): string
    {
        return $this->base;
    }

    /**
     * @param string $base
     *
     * @return $this
     */
    public function setBase(string $base): static
    {
        $this->base = $base;

        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @template T
     * @param string $name
     * @param array  $arguments
     *
     * @return PromiseInterface<T>|T
     */
    public function __call(string $name, array $arguments)
    {
        return str_ends_with($name, 'Async')
            ? $this
                ->setOption(RequestOptions::SYNCHRONOUS, false)
                ->_callAsync(substr($name, 0, -5), $arguments[0] ?? null)
            : $this
                ->setOption(RequestOptions::SYNCHRONOUS, true)
                ->_callAsync($name, $arguments[0] ?? null)->wait()
        ;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param string                $key
     * @param array|bool|int|string $option
     *
     * @return $this
     */
    public function setOption(string $key, array|bool|int|string $option): static
    {
        $this->options[$key] = $option;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        return $this->headers;
    }

    protected function _callAsync(string $action, mixed $data): PromiseInterface
    {
        return $this
            ->_sendRequest($action, $data)
            ->then(
                function (ResponseInterface $response): StreamInterface {
                    $stream = $response->getBody();
                    // Logger could fetch data from stream, try to rewind
                    $stream->tell() && $stream->rewind();

                    return $stream;
                },
                function (RequestException $exception) {
                    if ($exception->hasResponse()) {
                        /** @var ResponseInterface $response */
                        $response = $exception->getResponse();
                        if (!str_contains($response->getHeader('Content-Type')[0], $this::ACCEPT_CONTENT)) {
                            return new RejectedPromise(GuzzlePSR7Utils::streamFor($response->getReasonPhrase()));
                        }

                        $stream = $response->getBody();
                        // Logger could fetch data from stream, try to rewind
                        $stream->tell() && $stream->rewind();

                        return new RejectedPromise($stream);
                    }

                    return new RejectedPromise(GuzzlePSR7Utils::streamFor($exception->getMessage()));
                }
            )
            ->then(
                fn(StreamInterface $data) => $this->parseStream($data),
                fn(StreamInterface $data) => new RejectedPromise($this->parseStream($data))
            )
        ;
    }

    protected function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @param ClientInterface $client
     *
     * @return $this
     */
    protected function setClient(ClientInterface $client): static
    {
        $this->client = $client;

        return $this;
    }

    protected function getMethod(): string
    {
        return $this->method;
    }

    protected function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    protected function actionUri(string $action, mixed $data): string
    {
        return UriTemplate::expand($this->getPath(), ['command' => $action]);
    }

    protected function dataIndex(): string
    {
        return 'get' === $this->getMethod() ? RequestOptions::QUERY : RequestOptions::JSON;
    }

    protected function parseStream(StreamInterface $stream): object|array|string|null
    {
        try {
            return json_decode((string)$stream, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $stream->tell() && $stream->rewind();

            return $stream->getContents();
        }
    }

    /**
     * @param array $headers
     *
     * @return $this
     */
    protected function addHeaders(array $headers): static
    {
        $this->headers = array_replace($this->headers, $headers);

        return $this;
    }

    protected function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @return array<string,string|array|object>
     */
    protected function createConfig(): array
    {
        return [
            'base_uri'                     => $this->getBase(),
            RequestOptions::VERSION        => static::HTTP_VERSION,
            RequestOptions::VERIFY         => true,
            RequestOptions::DECODE_CONTENT => 'gzip',
            RequestOptions::HEADERS        => $this->getHeaders() + [
                    'Accept'     => static::ACCEPT_CONTENT,
                    'User-Agent' => $this->userAgent(GuzzleUtils::defaultUserAgent()),
                ],
            RequestOptions::AUTH           => $this->getAuth(),
            RequestOptions::COOKIES        => true,
            'handler' => HandlerStack::create($this->_getHandler()),
        ];
    }

    protected function _getHandler(): ?callable
    {
        return null;
    }

    protected function _sendRequest(string $action, mixed $data): PromiseInterface
    {
        return $this
            ->getClient()
            ->requestAsync(
                $this->getMethod(),
                $this->actionUri($action, $data),
                $this->getOptions() + [
                    RequestOptions::HEADERS => $this->getHeaders(),
                    $this->dataIndex() => $this->normalizer->normalize($data),
                ]
            )
        ;
    }
}
