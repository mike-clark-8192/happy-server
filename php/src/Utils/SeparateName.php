<?php

namespace Happy\Utils;

class SeparateName
{
    /**
     * Separate a full name into first and last name parts.
     *
     * @param string|null $fullName The full name to separate
     * @return array{firstName: string|null, lastName: string|null}
     */
    public static function separate(?string $fullName): array
    {
        if ($fullName === null || !is_string($fullName)) {
            return ['firstName' => null, 'lastName' => null];
        }

        $trimmedName = trim($fullName);

        if ($trimmedName === '') {
            return ['firstName' => null, 'lastName' => null];
        }

        // Split by whitespace (multiple spaces)
        $parts = preg_split('/\s+/', $trimmedName);

        if (count($parts) === 1) {
            return ['firstName' => $parts[0], 'lastName' => null];
        }

        $firstName = $parts[0];
        $lastName = implode(' ', array_slice($parts, 1));

        return ['firstName' => $firstName, 'lastName' => $lastName];
    }
}
