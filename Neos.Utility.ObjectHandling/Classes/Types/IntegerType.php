<?php
namespace Neos\Utility\Types;

class IntegerType
{
    public const STRING = 'int';

    public const STRING_LONG = 'integer';

    public static function stringDescribesMe(string $typeName): bool
    {
        return $typeName === static::STRING || $typeName === static::STRING_LONG;
    }

    public static function cast(mixed $input): int
    {
        return (int)$input;
    }
}
