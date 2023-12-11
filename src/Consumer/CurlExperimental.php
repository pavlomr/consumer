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

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\UriTemplate\UriTemplate;
use pavlomr\Service\Tools\Utils;
use Psr\Http\Message\StreamInterface;

class CurlExperimental extends CurlDecorator
{

    protected function _sendStreamRequest(string $action, mixed $data, array $options = []): string
    {
        if (is_array($data) && $data['stream'] instanceof StreamInterface) {
            $options = $options + [
                    CURLOPT_UPLOAD       => true,
                    CURLOPT_READFUNCTION => Utils::curlStreamReadFunction($data['stream']),
                    CURLOPT_INFILESIZE   => $data['stream']->getSize(),
                ];
        }

        return $this->_sendRequest($action, $data, $options);
    }

    protected function _sendRequest(string $action, mixed $data, array $options = []): string
    {
        return $this
            ->_exec(
                $options + [
                    CURLOPT_URL => UriResolver::resolve(
                        new Uri($this->getBase()),
                        new Uri($this->actionUri($action, $data))
                    ),
                ]
            )
        ;
    }

    protected function actionUri(string $action, mixed $data): string
    {
        return UriTemplate::expand($this->getPath(), ['command' => $action]);
    }

}
