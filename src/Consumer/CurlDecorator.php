<?php
/*
 * Copyright (c) 2022 Pavlo Marenyuk <pavlomr@gmail.com>
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

use pavlomr\Service\SingletonTrait;
use pavlomr\Service\UserAgentTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CurlDecorator implements DecoratorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SingletonTrait;
    use UserAgentTrait;

    protected const EXPOSE_INTERNALS = true;

    private string $base;
    private string $path;
    private array  $auth = [];
    /**
     * @var resource
     */
    private $client;

    public function __construct(array $curlOptions = [])
    {
        $this->setClient(curl_init());
        curl_setopt_array(
            $this->getClient(),
            $curlOptions + [
                CURLOPT_HEADER         => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => 'gzip',
                CURLOPT_AUTOREFERER    => true,
                CURLOPT_CONNECTTIMEOUT => 120,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_VERBOSE        => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => $this->userAgent(''),
                CURLOPT_USERNAME       => $this->getAuth()['username'] ?? null,
                CURLOPT_PASSWORD       => $this->getAuth()['password'] ?? null,
            ]
        );
    }

    public function __destruct()
    {
        curl_close($this->getClient());
    }

    /**
     * @inheritDoc
     */
    public function getAuth(): array
    {
        return $this->auth;
    }

    /**
     * @inheritDoc
     */
    public function setAuth($auth): DecoratorInterface
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function setPath(string $path): DecoratorInterface
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getBase(): string
    {
        return $this->base;
    }

    /**
     * @inheritDoc
     */
    public function setBase(string $base): DecoratorInterface
    {
        $this->base = $base;

        return $this;
    }

    protected function getClient()
    {
        return $this->client;
    }

    /**
     * @param resource $client
     *
     * @return $this
     */
    public function setClient($client): self
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

    /**
     * @param array $curlOptions
     *
     * @return bool|string
     */
    protected function _exec(array $curlOptions = [])
    {
        curl_setopt_array($this->getClient(), $curlOptions);

        return curl_exec($this->getClient());
    }
}