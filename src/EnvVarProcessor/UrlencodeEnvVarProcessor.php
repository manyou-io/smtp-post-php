<?php

declare(strict_types=1);

namespace App\EnvVarProcessor;

use Closure;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

use function urlencode;

class UrlencodeEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, Closure $getEnv): string
    {
        $env = $getEnv($name);

        return urlencode($env);
    }

    public static function getProvidedTypes(): array
    {
        return ['urlencode' => 'string'];
    }
}
