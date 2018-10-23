<?php

namespace pavlomr\Service;

use Exception;
use ReflectionClass;

trait UserAgentTrait
{
    /**
     * @return array
     */
    protected static function __config(): array
    {
        return [
            'major' => 'major',
            'minor' => 'minor',
            'app'   => pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_BASENAME),
        ];
    }

    /**
     * @param string $internal
     * @return string
     */
    protected function userAgent(string $internal): string
    {
        /** @var string $userAgent */
        static $userAgent = null;
        if (!$userAgent) {
            /** @var array $parents */
            $parents = [];
            try {
                /** @var ReflectionClass $reflection */
                $parent          = $reflection = new ReflectionClass(static::class);
                $callerClass     = $reflection->getShortName();
                $callerNamespace = $reflection->getNamespaceName();
                while ($parent = $parent->getParentClass()) {
                    $parents[] = $parent->getShortName();
                }
            } catch (Exception $exception) {
                $callerClass     = $callerClass ?? 'UnknownCaller';
                $callerNamespace = $callerNamespace ?? __NAMESPACE__;
            }
            $userAgent = trim(sprintf(
                '%s/%s/%s-%s (%s) %s %s',
                explode('\\', $callerNamespace)[0],
                $callerClass,
                static::__config()['major'],
                static::__config()['minor'],
                static::__config()['app'],
                join(' ', $parents),
                $this->__exposeInternals() ? $internal : ''
            ));
        }

        return $userAgent;
    }

    /**
     * @return bool
     */
    protected function __exposeInternals(): bool
    {
        return defined('static::EXPOSE_INTERNALS') ? constant('static::EXPOSE_INTERNALS') : true;
    }
}