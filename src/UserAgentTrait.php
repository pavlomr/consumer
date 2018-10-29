<?php

namespace pavlomr\Service;

use Exception;
use ReflectionClass;

trait UserAgentTrait
{
    /**
     * @param string $internal
     * @return string
     */
    protected function userAgent(string $internal): string
    {
        /** @var string $userAgent */
        static $userAgent = null;
        if (!$userAgent) {
            /** @var array $parentsChain */
            $parentsChain = [];
            try {
                /** @var ReflectionClass $reflection */
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
            $userAgent = trim(sprintf(
                '%s/%s/%s-%s (%s) %s %s',
                explode('\\', $callerNamespace)[0],
                $callerClass,
                $this->__callerVersionMajor(),
                $this->__callerVersionMinor(),
                $this->__callerApplication(),
                join(' ', $parentsChain),
                $this->__callerExposeInternals() ? $internal : ''
            ));
        }

        return $userAgent;
    }

    /**
     * Does decorator exposes internals
     * @return bool
     */
    protected function __callerExposeInternals(): bool
    {
        return defined('static::EXPOSE_INTERNALS') ? constant('static::EXPOSE_INTERNALS') : true;
    }

    /**
     * @return string
     */
    protected function __callerVersionMajor(): string
    {
        return '';
    }

    /**
     * @return string
     */
    protected function __callerVersionMinor(): string
    {
        return '';
    }

    /**
     * @return string
     */
    protected function __callerApplication(): string
    {
        return pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_BASENAME);
    }
}