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

use CurlHandle;
use pavlomr\Service\UserAgentTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CurlDecorator implements DecoratorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use UserAgentTrait;

    public const EXPOSE_INTERNALS = true;

    protected string   $base;
    protected string   $path;
    /** @var array<string> */
    private array      $auth    = [];
    private CurlHandle $client;
    private array      $options = [
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '*',
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_CONNECTTIMEOUT => 120,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE        => false,
        CURLOPT_RETURNTRANSFER => true,
    ];

    public function __construct(array $curlOptions = [])
    {
        $this->setClient(curl_init());
        curl_setopt_array(
            $this->getClient(),
            $curlOptions + $this->getOptions() + [
                CURLOPT_USERAGENT => $this->userAgent(''),
                CURLOPT_USERNAME  => $this->getAuth()['username'] ?? null,
                CURLOPT_PASSWORD  => $this->getAuth()['password'] ?? null,
            ]
        );
    }

    public function __destruct()
    {
        curl_close($this->getClient());
    }

    /**
     * @return string[]
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function setBase(string $base): static
    {
        $this->base = $base;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    protected function getClient(): CurlHandle
    {
        return $this->client;
    }

    /**
     * @param \CurlHandle $client
     *
     * @return $this
     */
    public function setClient(CurlHandle $client): static
    {
        $this->client = $client;

        return $this;
    }

    protected function mkUrl(...$arguments): string
    {
        return $this->getBase() .
            implode(
                '/',
                array_map(
                    fn($item) => rawurlencode($item),
                    explode('/', $this->mkPath(...$arguments))
                )
            );
    }

    protected function mkPath(...$arguments): string
    {
        return $this->getPath() . implode('/', $arguments);
    }

    protected function _exec(array $curlOptions = []): string
    {
        curl_setopt_array($this->getClient(), $curlOptions);

        return curl_exec($this->getClient());
    }
}