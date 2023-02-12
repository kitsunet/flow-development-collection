<?php
namespace Neos\Utility\Types;

/**
 *
 */
class ArrayType
{
    public const STRING = 'array';

    public const STRING_LONG = 'array';

    public static function stringDescribesMe(string $typeName): bool
    {
        return $typeName === static::STRING || $typeName === static::STRING_LONG;
    }
}
