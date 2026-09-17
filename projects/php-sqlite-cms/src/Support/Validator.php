<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * Minimal validation helper used by admin forms and the JSON API.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules e.g. ['title' => 'required|max:120']
     * @param array<string, string> $labels
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $stringValue = is_scalar($value) ? trim((string) $value) : '';

            foreach (explode('|', $ruleString) as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

                match ($name) {
                    'required' => $this->checkRequired($field, $label, $stringValue),
                    'email' => $stringValue === '' ?: $this->checkEmail($field, $label, $stringValue),
                    'min' => $stringValue === '' ?: $this->checkMin($field, $label, $stringValue, (int) $parameter),
                    'max' => $stringValue === '' ?: $this->checkMax($field, $label, $stringValue, (int) $parameter),
                    'slug' => $stringValue === '' ?: $this->checkSlug($field, $label, $stringValue),
                    'in' => $stringValue === '' ?: $this->checkIn($field, $label, $stringValue, (string) $parameter),
                    'date' => $stringValue === '' ?: $this->checkDate($field, $label, $stringValue),
                    'url' => $stringValue === '' ?: $this->checkUrl($field, $label, $stringValue),
                    'same' => $this->checkSame($field, $label, $stringValue, (string) $parameter),
                    default => null,
                };
            }
        }
    }

    private function checkRequired(string $field, string $label, string $value): void
    {
        if ($value === '') {
            $this->addError($field, sprintf('%s is required.', $label));
        }
    }

    private function checkEmail(string $field, string $label, string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, sprintf('%s must be a valid email address.', $label));
        }
    }

    private function checkMin(string $field, string $label, string $value, int $min): void
    {
        if (mb_strlen($value) < $min) {
            $this->addError($field, sprintf('%s must be at least %d characters.', $label, $min));
        }
    }

    private function checkMax(string $field, string $label, string $value, int $max): void
    {
        if (mb_strlen($value) > $max) {
            $this->addError($field, sprintf('%s may not be longer than %d characters.', $label, $max));
        }
    }

    private function checkSlug(string $field, string $label, string $value): void
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            $this->addError($field, sprintf('%s may only contain lowercase letters, numbers and hyphens.', $label));
        }
    }

    private function checkIn(string $field, string $label, string $value, string $list): void
    {
        $allowed = explode(',', $list);
        if (!in_array($value, $allowed, true)) {
            $this->addError($field, sprintf('%s is not an allowed value.', $label));
        }
    }

    private function checkDate(string $field, string $label, string $value): void
    {
        if (strtotime($value) === false) {
            $this->addError($field, sprintf('%s must be a valid date.', $label));
        }
    }

    private function checkUrl(string $field, string $label, string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->addError($field, sprintf('%s must be a valid URL.', $label));
        }
    }

    private function checkSame(string $field, string $label, string $value, string $other): void
    {
        if ($value !== (string) ($this->data[$other] ?? '')) {
            $this->addError($field, sprintf('%s does not match %s.', $label, $other));
        }
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function flatErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $messages) {
            foreach ($messages as $message) {
                $flat[] = $message;
            }
        }

        return $flat;
    }
}
