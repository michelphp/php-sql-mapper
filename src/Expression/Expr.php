<?php

namespace Michel\SqlMapper\Expression;

/**
 * @package	php-query-builder
 * @author	PhpDevCommunity <dev@phpdevcommunity.com>
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://www.phpdevcommunity.com
 */
final class Expr
{
    /**
     * Returns an SQL expression for equal comparison.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function equal(string $key, string $value): string
    {
        return "$key = $value";
    }

    /**
     * Returns an SQL expression for not equal comparison.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function notEqual(string $key, string $value): string
    {
        return "$key <> $value";
    }

    /**
     * Returns an SQL expression for greater than comparison.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function greaterThan(string $key, string $value): string
    {
        return "$key > $value";
    }

    /**
     * Returns an SQL expression for greater than or equal comparison.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function greaterThanEqual(string $key, string $value): string
    {
        return "$key >= $value";
    }

    /**
     * Returns an SQL expression for lower than comparison.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function lowerThan(string $key, string $value): string
    {
        return "$key < $value";
    }

    /**
     * Returns an SQL expression for lower than or equal comparison.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public static function lowerThanEqual(string $key, string $value): string
    {
        return "$key <= $value";
    }

    /**
     * Returns an SQL expression for checking if a column is NULL.
     *
     * @param string $key
     * @return string
     */
    public static function isNull(string $key): string
    {
        return "$key IS NULL";
    }

    /**
     * Returns an SQL expression for checking if a column is NOT NULL.
     *
     * @param string $key
     * @return string
     */
    public static function isNotNull(string $key): string
    {
        return "$key IS NOT NULL";
    }

    /**
     * Returns an SQL expression for checking if a value is in a list of values.
     *
     * @param string $key
     * @param array $values
     * @return string
     */
    public static function in(string $key, array $values): string
    {
        return "$key IN " . '(' . implode(', ', array_map([self::class, 'value'], $values)) . ')';
    }

    /**
     * Returns an SQL expression for checking if a value is not in a list of values.
     *
     * @param string $key
     * @param array $values
     * @return string
     */
    public static function notIn(string $key, array $values): string
    {
        return "$key NOT IN (" . implode(', ', array_map([self::class, 'value'], $values)) . ')';
    }

    /**
     * Returns a value of an IN list as SQL: a placeholder as is, a number as is, a string quoted.
     *
     * A quote is escaped by doubling it, which every SQL database understands. A backslash is refused, because
     * MySQL and MariaDB read it as an escape character and could end the string early: such a value must be bound
     * as a parameter.
     *
     * @param mixed $value
     * @return string
     */
    private static function value($value): string
    {
        if (is_int($value)) {
            return (string)$value;
        }

        if (is_float($value) && is_finite($value)) {
            return (string)$value;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('An IN value must be an int, a float or a string, %s given.', get_debug_type($value)));
        }

        if ($value === '?' || preg_match('/^:[A-Za-z_]\w*$/', $value) === 1) {
            return $value;
        }

        if (strpbrk($value, "\\\0") !== false) {
            throw new \InvalidArgumentException('An IN value with a backslash or a NUL byte must be bound as a parameter.');
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }
}
