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

use pavlomr\Service\SingletonTrait;
use pavlomr\Service\UserAgentTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SoapClient;
use SoapFault;

abstract class SOAPDecorator implements DecoratorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SingletonTrait;
    use UserAgentTrait;

    protected const URI          = 'urn:pavlomr:Service:Provider:Abstract';
    protected const WSDL         = null;
    protected const SOAP_VERSION = SOAP_1_2;
    private array      $headers = [
        'Accept' => ['application/soap+xml', 'application/xml', 'text/xml'],
    ];
    private SoapClient $client;
    private string     $path    = '';
    private array      $auth    = [];
    private string     $base    = '';

    /**
     * SOAPDecorator constructor.
     * @throws SoapFault
     */
    public function __construct()
    {
        $options = [
                'uri'            => static::getServiceURI(), // uri ignored in wsdl mode
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
            ] + $this->getAuth();
        // overwrite wsdl location if set. Mandatory for non-wsdl
        $locationOverride = $this->getBase() . $this->getPath();
        if (!empty($locationOverride)) {
            $options['location'] = $locationOverride;
        }
        $this->setClient(
            new SoapClient(static::WSDL, $options)
        );
    }

    public function getAuth(): array
    {
        return $this->auth;
    }

    public function setAuth($auth): DecoratorInterface
    {
        $this->auth = $auth;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): DecoratorInterface
    {
        $this->path = $path;

        return $this;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function setBase(string $base): DecoratorInterface
    {
        $this->base = $base;

        return $this;
    }

    public static function getServiceURI(): string
    {
        return static::URI;
    }

    /**
     * @return SoapClient
     */
    public function getClient(): SoapClient
    {
        return $this->client;
    }

    /**
     * @param SoapClient $client
     *
     * @return SOAPDecorator
     */
    public function setClient(SoapClient $client): SOAPDecorator
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return string
     */
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
}