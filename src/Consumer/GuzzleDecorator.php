<?php
/*
 * Copyright (c) 2021 Pavlo Marenyuk <pavlomr@gmail.com>
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
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Utils as GuzzlePSR7Utils;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\UriTemplate\UriTemplate;
use GuzzleHttp\Utils as GuzzleUtils;
use JsonException;
use pavlomr\Service\SingletonTrait;
use pavlomr\Service\UserAgentTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

use function json_decode;

/**
 * Class GuzzleDecorator
 * @method mixed _exec(array $params)
 */
abstract class GuzzleDecorator implements DecoratorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SingletonTrait;
    use UserAgentTrait;

    protected const         HTTP_VERSION   = 1.1;
    protected const         ACCEPT_CONTENT = 'application/json';

    protected string        $path;
    protected string        $method  = 'post';
    private array           $auth    = [];
    private ClientInterface $client;
    private string          $base;
    private array           $headers = [];
    private array           $options = [];

    /**
     * Api constructor.
     *
     * @param callable[] $handlers
     */
    protected function __construct(array $handlers = [])
    {
        $config = $this->createConfig();
        foreach ($handlers as $handler) {
            $config['handler']->push($handler);
        }

        $this
            ->setClient(new Client($config))
            ->setLogger($this->logger ?? new NullLogger())
        ;
    }

    /**
     * @return string[]
     */
    public function getAuth(): array
    {
        return $this->auth;
    }

    /**
     * @param $auth
     *
     * @return DecoratorInterface
     */
    public function setAuth($auth): DecoratorInterface
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
     * @return GuzzleDecorator
     */
    public function setBase(string $base): DecoratorInterface
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
     * @return DecoratorInterface
     */
    public function setPath(string $path): DecoratorInterface
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return PromiseInterface|mixed
     * @throws GuzzleException
     */
    public function __call(string $name, array $arguments)
    {
        return substr($name, -5) === 'Async'
            ? $this->_callAsync(substr($name, 0, -5), $arguments[0])
            : $this->_callAsync($name, $arguments[0])->wait(true);
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
     * @return GuzzleDecorator
     */
    public function setOptions(array $options): GuzzleDecorator
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        return $this->headers;
    }

    protected function _callAsync(string $action, $data): PromiseInterface
    {
        return $this
            ->getClient()
            ->requestAsync(
                $this->getMethod(),
                $this->actionUri($action, $data),
                $this->getOptions() + [
                    RequestOptions::HEADERS => $this->getHeaders(),
                    $this->dataIndex()      => $data,
                ]
            )
            ->then(
                function (ResponseInterface $response) {
                    return $response->getBody();
                },
                function (RequestException $exception) {
                    if ($exception->hasResponse()) {
                        /** @var ResponseInterface $response */
                        $response = $exception->getResponse();
                        if (false === strpos($response->getHeader('Content-Type')[0], $this::ACCEPT_CONTENT)) {
                            return new RejectedPromise(GuzzlePSR7Utils::streamFor($response->getReasonPhrase()));
                        }

                        return new RejectedPromise($response->getBody());
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

    /**
     * @return ClientInterface
     */
    protected function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @param ClientInterface $client
     *
     * @return GuzzleDecorator
     */
    protected function setClient(ClientInterface $client): GuzzleDecorator
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     *
     * @return GuzzleDecorator
     */
    protected function setMethod(string $method): GuzzleDecorator
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param string $action
     *
     * @return string
     */
    protected function actionUri(string $action, $data): string
    {
        return UriTemplate::expand(
            $this->getPath(),
            ['command' => $action]
        );
    }

    protected function dataIndex(): string
    {
        return 'get' === $this->getMethod() ? RequestOptions::QUERY : RequestOptions::JSON;
    }

    /**
     * @param StreamInterface $stream
     *
     * @return array|object|string
     */
    protected function parseStream(StreamInterface $stream)
    {
        $stream->rewind();
        try {
            return json_decode($stream, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $stream->rewind();

            return $stream->getContents();
        }
    }

    /**
     * @param array $headers
     *
     * @return GuzzleDecorator
     */
    protected function addHeaders(array $headers): GuzzleDecorator
    {
        $this->headers = array_replace($this->headers, $headers);

        return $this;
    }

    protected function setHeader(string $name, string $value): GuzzleDecorator
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @return array
     */
    protected function createConfig(): array
    {
        $config = [
            RequestOptions::VERIFY         => true,
            RequestOptions::DECODE_CONTENT => 'gzip',
            RequestOptions::HEADERS        => [
                    'Accept'     => static::ACCEPT_CONTENT,
                    'User-Agent' => $this->userAgent(GuzzleUtils::defaultUserAgent()),
                ] + $this->getHeaders(),
            RequestOptions::AUTH           => $this->getAuth(),
            RequestOptions::COOKIES        => true,
            RequestOptions::STREAM         => true,
            RequestOptions::VERSION        => static::HTTP_VERSION,
            'handler'                      => HandlerStack::create(),
        ];
        if ($this->getBase() !== null) {
            $config['base_uri'] = $this->getBase();
        }

        return $config;
    }
}
