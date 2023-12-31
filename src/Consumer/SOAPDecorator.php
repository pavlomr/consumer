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

use pavlomr\Service\UserAgentTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SoapClient;
use SoapFault;

abstract class SOAPDecorator implements DecoratorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use UserAgentTrait;

    protected const SOAP_VERSION = SOAP_1_2;
    protected const URI          = 'urn:pavlomr:Service:Provider:Abstract';

    protected string $base;
    protected string $path;
    private array      $auth    = [];
    private SoapClient $client;
    private array      $headers = [
        'Accept' => ['application/soap+xml', 'application/xml', 'text/xml'],
    ];
    private ?string    $wsdl;
    private array      $options = [];

    /**
     * SOAPDecorator constructor.
     *
     * @param string|null $wsdl
     *
     * @throws SoapFault
     */
    public function __construct(?string $wsdl = null)
    {
        $this
            ->setWsdl($wsdl)
            ->setClient(
                new SoapClient(
                    $this->getWsdl(),
                    $this->getOptions() + [
                        'uri'            => static::getServiceURI(), # uri ignored in wsdl mode
                        'soap_version'   => static::SOAP_VERSION,
                        'trace'          => true,
                        'exceptions'     => true,
                        'user_agent'     => $this->userAgent(SoapClient::class),
                        'stream_context' => stream_context_create(
                            [
                                'http' => [
                                    'header' => $this->getHeaderString(),
                                ],
                            ]
                        ),
                    ]
                    + $this->getAuth()
                )
            )
        ;
    }

    public static function getServiceURI(): string
    {
        return static::URI;
    }

    public function getAuth(): array
    {
        return $this->auth;
    }

    /**
     * @param array $auth
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

    public function getClient(): SoapClient
    {
        return $this->client;
    }

    /**
     * @param SoapClient $client
     *
     * @return $this
     */
    public function setClient(SoapClient $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getHeaderString(): string
    {
        $ret = '';
        foreach ($this->headers as $name => $header) {
            if (is_array($header)) {
                $header = implode(',', $header);
            }
            $ret .= "$name: $header\n";
        }

        return trim($ret);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getClient()->{$name}(...$arguments);
    }

    public function getWsdl(): ?string
    {
        return $this->wsdl;
    }

    /**
     * @param string|null $wsdl
     *
     * @return $this
     */
    public function setWsdl(?string $wsdl): static
    {
        $this->wsdl = $wsdl;

        return $this;
    }

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
     * @return string
     * @deprecated set location in @see self::getOptions()
     */
    protected function getLocation(): string
    {
        return $this->getBase() . $this->getPath();
    }
}