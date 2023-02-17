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

class CurlSFTP extends CurlDecorator
{
    public function __construct(array $curlOptions = [])
    {
        parent::__construct(
            $curlOptions + [
                CURLOPT_PROTOCOLS               => CURLPROTO_SFTP,
                CURLOPT_FILETIME                => true,
                CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY,
                CURLOPT_FTPLISTONLY             => true,
                CURLOPT_SSH_PRIVATE_KEYFILE     => $this->getAuth()['keyfile'] ?? null,
                CURLOPT_KEYPASSWD               => $this->getAuth()['keypasswd'] ?? null,
            ]
        );
    }

    /**
     * @param string $dir
     *
     * @return bool|string
     */
    public function getList(string $dir = '')
    {
        return $this->_exec(
            [
                CURLOPT_HTTPGET        => true,
                CURLOPT_URL            => $this->mkUrl($dir),
                CURLOPT_QUOTE          => [],
                CURLOPT_INFILE         => null,
                CURLOPT_FILE           => STDOUT,
                CURLOPT_RETURNTRANSFER => true,
            ]
        );
    }

    /**
     * @param string               $name
     * @param string|resource|null $outputStream
     *
     * @return bool|string
     */
    public function getItem($name, $outputStream = null)
    {
        return $this->_exec(
            [
                CURLOPT_HTTPGET => true,
                CURLOPT_URL     => $this->mkUrl($name),
                CURLOPT_FILE    => $outputStream,
                CURLOPT_INFILE  => null,
                CURLOPT_QUOTE   => [],
            ]
        );
    }

    /**
     * @param string          $name
     * @param string|resource $inputStream
     *
     * @return bool|string
     */
    public function putItem($name, $inputStream)
    {
        return $this->_exec(
            [
                CURLOPT_UPLOAD => true,
                CURLOPT_URL    => $this->mkUrl($name),
                CURLOPT_INFILE => $inputStream,
                CURLOPT_QUOTE  => [],
            ]
        );
    }

    /**
     * @param string $src
     * @param string $dst
     *
     * @return bool|string
     */
    public function mvItem($src, $dst)
    {
        return $this->_exec(
            [
                CURLOPT_HTTPGET        => true,
                CURLOPT_URL            => $this->mkUrl(),
                CURLOPT_QUOTE          => [sprintf('rename %s %s', $this->mkPath($src), $this->mkPath($dst))],
                CURLOPT_INFILE         => null,
                CURLOPT_FILE           => STDOUT,
                CURLOPT_RETURNTRANSFER => true,
            ]
        );
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public function rmItem($name)
    {
        return $this->_exec(
            [
                CURLOPT_HTTPGET        => true,
                CURLOPT_URL            => $this->mkUrl(),
                CURLOPT_QUOTE          => [sprintf('rm %s ', $this->mkPath($name))],
                CURLOPT_INFILE         => null,
                CURLOPT_FILE           => STDOUT,
                CURLOPT_RETURNTRANSFER => true,
            ]
        );
    }

    /**
     * @param string|null $mask
     * @param string      $dir
     *
     * @return iterable
     */
    public function getFiles(string $mask = null, string $dir = ''): iterable
    {
        foreach (explode(PHP_EOL, $this->getList($dir)) as $item) {
            if (!empty($mask)) {
                if (preg_match($mask, $item, $matches)) {
                    yield $item;
                }
            } else {
                yield $item;
            }
        }
    }
}