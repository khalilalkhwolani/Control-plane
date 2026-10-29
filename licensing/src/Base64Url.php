<?php

declare(strict_types=1);

namespace SuperPro\ControlPlane\Licensing;

final class Base64Url
{
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
