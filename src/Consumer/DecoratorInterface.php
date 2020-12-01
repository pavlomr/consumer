<?php

namespace pavlomr\Service\Consumer;

interface DecoratorInterface
{
    /**
     * @return string[]
     */
    public function getAuth(): array;

    /**
     * @param $auth
     * @return DecoratorInterface
     */
    public function setAuth($auth): DecoratorInterface;

    /**
     * @return string
     */
    public function getPath(): string;

    /**
     * @param string $path
     * @return DecoratorInterface
     */
    public function setPath(string $path): DecoratorInterface;

    /**
     * @return string
     */
    public function getBase(): string;

    /**
     * @param string $base
     * @return DecoratorInterface
     */
    public function setBase(string $base): DecoratorInterface;
}