<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Normalizes and validates form input for items and groups.
 *
 * Each method returns the cleaned data and a map of field => message; an empty map means valid.
 */
final class TimelineInput {
    private const string MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/';
    private const string COLOR = '/^#[0-9a-fA-F]{6}$/';
    private const string DEFAULT_COLOR = '#3498db';

    /**
     * @param array<string, mixed> $data
     * @param callable(int): bool  $groupExists
     *
     * @return array{array<string, mixed>, array<string, string>}
     */
    public static function item(array $data, callable $groupExists): array {
        $errors = [];
        $clean = $data;

        $title = self::string($data, 'title');
        if ($title === '') {
            $errors['title'] = 'Title is required';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Title must be at most 200 characters';
        }
        $clean['title'] = $title;

        $groupId = filter_var($data['groupId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($groupId === false || !$groupExists($groupId)) {
            $errors['groupId'] = 'Group does not exist';
        }
        $clean['groupId'] = $groupId === false ? 0 : $groupId;

        [$clean, $errors] = self::dates($data, $clean, $errors);

        $color = self::string($data, 'color');
        if ($color !== '' && preg_match(self::COLOR, $color) !== 1) {
            $errors['color'] = 'Color must be a hex value like #3498db';
        }
        $clean['color'] = $color === '' ? null : $color;

        $description = self::string($data, 'description');
        if (mb_strlen($description) > 2000) {
            $errors['description'] = 'Description must be at most 2000 characters';
        }
        $clean['description'] = $description === '' ? null : $description;

        return [$clean, $errors];
    }

    /**
     * Start and end month of a resize.
     *
     * @param array<string, mixed> $data
     *
     * @return array{array<string, mixed>, array<string, string>}
     */
    public static function resize(array $data): array {
        return self::dates($data, $data, []);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{array<string, mixed>, array<string, string>}
     */
    public static function group(array $data): array {
        $errors = [];
        $clean = $data;

        $name = self::string($data, 'name');
        if ($name === '') {
            $errors['name'] = 'Name is required';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must be at most 100 characters';
        }
        $clean['name'] = $name;

        $icon = self::string($data, 'icon');
        if (mb_strlen($icon) > 16) {
            $errors['icon'] = 'Icon must be at most 16 characters';
        }
        $clean['icon'] = $icon === '' ? '📁' : $icon;

        $color = self::string($data, 'color');
        if ($color !== '' && preg_match(self::COLOR, $color) !== 1) {
            $errors['color'] = 'Color must be a hex value like #3498db';
        }
        $clean['color'] = $color === '' ? self::DEFAULT_COLOR : $color;

        return [$clean, $errors];
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, mixed>  $clean
     * @param array<string, string> $errors
     *
     * @return array{array<string, mixed>, array<string, string>}
     */
    private static function dates(array $data, array $clean, array $errors): array {
        $start = self::string($data, 'startDate');
        if (preg_match(self::MONTH, $start) !== 1) {
            $errors['startDate'] = 'Start date must be a month like 2024-03';
        }
        $clean['startDate'] = $start;

        $end = self::string($data, 'endDate');
        if ($end !== '' && preg_match(self::MONTH, $end) !== 1) {
            $errors['endDate'] = 'End date must be a month like 2024-03';
        } elseif ($end !== '' && !isset($errors['startDate']) && $end < $start) {
            $errors['endDate'] = 'End date must not be before the start date';
        }
        $clean['endDate'] = $end === '' ? null : $end;

        return [$clean, $errors];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key): string {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? mb_trim((string) $value) : '';
    }
}
