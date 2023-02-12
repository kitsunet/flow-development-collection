<?php
namespace Neos\Utility\Types;

class BooleanType
{
    public const STRING = 'bool';
    public const STRING_LONG = 'boolean';

    public static function stringDescribesMe(string $typeName): bool
    {
        return $typeName === static::STRING || $typeName === static::STRING_LONG;
    }

    public static function cast(mixed $input): bool
    {
        return (boolean)$input;
    }
}
