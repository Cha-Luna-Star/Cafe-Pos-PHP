<?php

function inventoryToday(): DateTimeImmutable
{
    return new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
}

function parseInventoryDate(string $value): ?DateTimeImmutable
{
    if (!preg_match('/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));

    return $date && $date->format('Y-m-d') === $value ? $date : null;
}

function batchExpirationStatus(?string $value, DateTimeImmutable $today): string
{
    if ($value === null) {
        return 'no-expiration';
    }

    $date = parseInventoryDate($value);
    if ($date === null) {
        return 'invalid-date';
    }

    if ($date < $today) {
        return 'expired';
    }

    return $date <= $today->modify('+7 days') ? 'expiring-soon' : 'valid';
}
