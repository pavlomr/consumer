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

namespace pavlomr\Service;

use Exception;
use ReflectionClass;

use function curl_version;

trait UserAgentTrait
{
    /**
     * @param string $internal
     *
     * @return string
     */
    protected function userAgent(string $internal): string
    {
        /** @var string $userAgent */
        static $userAgent = null;
        if (!$userAgent) {
            $parentsChain = [];
            try {
                $parent          = $reflection = new ReflectionClass(static::class);
                $callerClass     = $reflection->getShortName();
                $callerNamespace = $reflection->getNamespaceName();
                while ($parent = $parent->getParentClass()) {
                    $parentsChain[] = $parent->getShortName();
                }
            } catch (Exception $exception) {
                $callerClass     = $callerClass ?? 'UnknownCaller';
                $callerNamespace = $callerNamespace ?? __NAMESPACE__;
            }
            $userAgent = trim(
                sprintf(
                    '%s/%s/%s-%s (%s) %s %s',
                    explode('\\', $callerNamespace)[0],
                    $callerClass,
                    $this->_callerVersionMajor(),
                    $this->_callerVersionMinor(),
                    $this->_callerApplication(),
                    implode(' ', $parentsChain),
                    $this->_callerExposeInternals() ? sprintf(
                        '%s PHP/%s curl/%s',
                        $internal,
                        PHP_VERSION,
                        curl_version()['version']
                    ) : ''
                )
            );
        }

        return $userAgent;
    }

    /**
     * @return string
     */
    protected function _callerVersionMajor(): string
    {
        return $_SERVER['TIER'] ?? 'dev';
    }

    /**
     * @return string
     */
    protected function _callerVersionMinor(): string
    {
        return $_SERVER['TAG'] ?? '*';
    }

    /**
     * @return string
     */
    protected function _callerApplication(): string
    {
        return pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_BASENAME);
    }

    /**
     * Does decorator exposes internals
     * @return bool
     */
    protected function _callerExposeInternals(): bool
    {
        return defined('static::EXPOSE_INTERNALS') ? constant('static::EXPOSE_INTERNALS') : true;
    }
}