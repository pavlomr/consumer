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

namespace pavlomr\Service\Tools;

use Closure;

final class Utils
{
    /**
     * @template T of array<string>
     *
     * @param iterable|object $data
     * @param \Closure|null   $keyName
     *
     * @return non-empty-array<int, T>|empty
     */
    public static function asNameContents($data, Closure $keyName = null)
    {
        if (null === $keyName) {
            $keyName = static fn($i): string => $i;
        }
        $res = [];
        foreach ($data as $key => $value) {
            if (is_iterable($value)) {
                foreach (
                    self::asNameContents($value, static fn($i): string => sprintf('%s[%s]', $key, $i)) as $recursive
                ) {
                    $res[] = $recursive;
                }
            } else {
                $res[] = ['name' => $keyName($key), 'contents' => $value];
            }
        }

        return $res;
    }

}