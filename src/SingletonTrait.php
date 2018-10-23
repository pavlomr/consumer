<?php

namespace pavlomr\Service;


trait SingletonTrait
{
    /**
     * @var self
     */
    private static $_map = [];

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!isset(self::$_map[static::class])) {
            self::$_map[static::class] = new static();
        }

        return self::$_map[static::class];
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return static::getInstance()->$name(...$arguments);
//        return static::getInstance()->getClient()->$name(...$arguments);
    }

//    protected function __clone() { }
}
