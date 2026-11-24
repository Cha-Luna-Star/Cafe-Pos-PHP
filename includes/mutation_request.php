<?php

function hasValidCsrfToken(mixed $provided): bool
{
    $expected = $_SESSION['csrf_token'] ?? null;
    return is_string($expected) && $expected !== '' &&
        is_string($provided) && hash_equals($expected, $provided);
}

function requestSqlInt(mixed $value, int $min = 1): int|false
{
    if (!is_int($value) && !is_string($value)) {
        return false;
    }

    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => 2147483647]
    ]);
}
