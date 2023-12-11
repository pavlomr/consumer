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

namespace pavlomr\Service\Consumer\Tests;

use GuzzleHttp\UriTemplate\UriTemplate;
use pavlomr\Service\Consumer\CurlExperimental;
use Psr\Http\Message\StreamInterface;

class Storage extends CurlExperimental
{
    protected const  ACCEPT_CONTENT = 'application/json';

    protected string $path = '{action}{/actionId}';
    private array    $headers;

    public function __construct(string $base, string|array $auth, array $curlOptions = [])
    {
        $auth = (is_string($auth)) ? ['token' => $auth] : $auth;
        $this
            ->setBase($base)
            ->setAuth($auth + [2 => 'token'])
            ->addHeader('Cache-control', ['private', 'max-age=0'])
            ->addHeader('Accept', static::ACCEPT_CONTENT)
        ;

        parent::__construct([CURLOPT_VERBOSE => 1,] + $curlOptions);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->_sendRequest($name, $arguments);
    }

    public function addHeader(string $name, string|array $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function putContent(int|string $object, StreamInterface $content): array
    {
        return json_decode(
            $this
                ->_sendStreamRequest(
                    action: 'content',
                    data:   [
                                'actionId' => $object,
                                'stream'   => $content,
                            ]
                ),
            JSON_OBJECT_AS_ARRAY
        );
    }

    protected function actionUri(string $action, mixed $data): string
    {
        return UriTemplate::expand($this->getPath(), [
            'actionId' => $data['actionId'],
            'action'   => $action,
        ]);
    }

    public function setAuth($auth): static
    {
        return parent
            ::setAuth($auth)
            ->addHeader('Authorization', "Bearer {$this->getAuth()['token']}",)
        ;
    }

    protected function _exec(array $curlOptions = []): string
    {
        return parent::_exec(
            [
                CURLOPT_HTTPHEADER => $this->getHeaderString(),
            ] + $curlOptions
        );
    }

    protected function getHeaderString(): array
    {
        $ret = [];
        foreach ($this->headers as $name => $header) {
            if (is_array($header)) {
                $header = implode(',', $header);
            }
            $ret[] = "$name: $header";
        }

        return $ret;
    }
}