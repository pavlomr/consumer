<?php

namespace pavlomr\Service\Consumer;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use pavlomr\Service\SingletonTrait;
use pavlomr\Service\UserAgentTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

use function GuzzleHttp\default_user_agent;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\uri_template;

/**
 * Class GuzzleDecorator
 * @method mixed _exec(array $params)
 */
abstract class GuzzleDecorator implements DecoratorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SingletonTrait;
    use UserAgentTrait;

    protected const ACCEPT_CONTENT = 'application/json';
    protected string        $path;
    protected string        $method  = 'post';
    private array           $auth    = [];
    private ClientInterface $client;
    private string          $base;
    private array           $headers = [];

    /**
     * Api constructor.
     *
     * @param callable[] $handlers
     */
    protected function __construct(array $handlers = [])
    {
        $this->setLogger($this->logger ?? new NullLogger());
        $config = [
            RequestOptions::VERIFY         => true,
            RequestOptions::DECODE_CONTENT => 'gzip',
            RequestOptions::HEADERS        => [
                    'Accept'     => static::ACCEPT_CONTENT,
                    'User-Agent' => $this->userAgent(default_user_agent()),
                ] + $this->getHeaders(),
            RequestOptions::AUTH           => $this->getAuth(),
            RequestOptions::COOKIES        => true,
        ];
        if ($this->getBase() !== null) {
            $config['base_uri'] = $this->getBase();
        }

        $stack = HandlerStack::create();
        foreach ($handlers as $handler) {
            $stack->push($handler);
        }
        $config['handler'] = $stack;
        $this->setClient(new Client($config));
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
     * @return PromiseInterface|StreamInterface
     * @throws GuzzleException
     */
    public function __call(string $name, array $arguments)
    {
        return substr($name, -5) === 'Async'
            ? $this->_callAsync(substr($name, 0, -5), $arguments[0])
            : $this->_callSync($name, $arguments[0]);
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
                $this->actionUri($action),
                [$this->dataIndex() => $data]
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
                            return Create::rejectionFor($response->getReasonPhrase());
                        }

                        return Create::rejectionFor($response->getBody());
                    }

                    return Create::rejectionFor($exception->getMessage());
                }
            )
            ->then(
                function (StreamInterface $data) {
                    return $this->parseStream($data);
                },
                function (StreamInterface $data) {
                    return Create::rejectionFor($this->parseStream($data));
                }
            )
            ;
    }

    /**
     * @param string $action
     * @param        $data
     *
     * @return StreamInterface
     * @throws GuzzleException
     */
    protected function _callSync(string $action, $data): StreamInterface
    {
        return $this->parseStream(
            $this
                ->getClient()
                ->request(
                    $this->getMethod(),
                    $this->actionUri($action),
                    [
                        RequestOptions::HEADERS => $this->getHeaders(),
                        $this->dataIndex()      => $data,
                    ]
                )
                ->getBody()
        );
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
     * @param string $function
     *
     * @return string
     */
    protected function actionUri(string $function): string
    {
        return uri_template(
            $this->getPath(),
            ['token' => $this->getAuth()['token'], 'command' => $function]
        );
    }

    protected function dataIndex(): string
    {
        return $this->getMethod() === 'get' ? RequestOptions::QUERY : RequestOptions::JSON;
    }

    /**
     * @param StreamInterface $stream
     *
     * @return mixed
     */
    protected function parseStream(StreamInterface $stream)
    {
        try {
            $stream->rewind();

            return json_decode($stream);
        } catch (InvalidArgumentException $exception) {
            return new class($stream) {
                public string $message;

                public function __construct(StreamInterface $stream)
                {
                    $stream->rewind();
                    $this->message = $stream->getContents();
                }
            };
        }
    }

    /**
     * @param array $header
     *
     * @return GuzzleDecorator
     */
    protected function addHeaders(array $header): GuzzleDecorator
    {
        $this->headers += $header;

        return $this;
    }

    protected function setHeader(string $name, string $value): GuzzleDecorator
    {
        $this->headers[$name] = $value;

        return $this;
    }
}
