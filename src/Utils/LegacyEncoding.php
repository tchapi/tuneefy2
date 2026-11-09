<?php

namespace App\Utils;

/**
 * Items stored by the previous versions of tuneefy may hold ISO-8859-1 strings
 * in their serialized object: every such string is converted back to UTF-8 on load.
 */
final class LegacyEncoding
{
    /**
     * Repairs, in place, every string held by the object (and the objects it holds).
     */
    public static function repair(object $object, array &$seen = []): void
    {
        if (\in_array($object, $seen, true)) {
            return;
        }
        $seen[] = $object;

        for ($class = new \ReflectionObject($object); $class; $class = $class->getParentClass() ?: null) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || !$property->isInitialized($object)) {
                    continue;
                }

                $value = $property->getValue($object);
                $repaired = self::repairValue($value, $seen);

                if ($repaired !== $value) {
                    $property->setValue($object, $repaired);
                }
            }
        }
    }

    public static function repairString(string $string): string
    {
        // Valid UTF-8 (or plain ASCII) is left untouched
        if (false !== preg_match('//u', $string)) {
            return $string;
        }

        $converted = iconv('ISO-8859-1', 'UTF-8', $string);

        // iconv() fails on bytes it cannot convert: keep the original rather than lose it
        return false === $converted ? $string : $converted;
    }

    private static function repairValue(mixed $value, array &$seen): mixed
    {
        if (\is_string($value)) {
            return self::repairString($value);
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::repairValue($item, $seen);
            }

            return $value;
        }

        if (\is_object($value)) {
            self::repair($value, $seen);
        }

        return $value;
    }
}
