<?php
namespace Neos\Utility\Types;

class StringType
{
    public const STRING = 'string';

    public const STRING_LONG = 'string';

    public static function stringDescribesMe(string $typeName): bool
    {
        return $typeName === static::STRING || $typeName === static::STRING_LONG;
    }

    public static function cast(mixed $input): int
    {
        return (string)$input;
    }
}
