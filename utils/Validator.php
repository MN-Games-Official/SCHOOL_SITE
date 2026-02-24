<?php
/**
 * ============================================================================
 * Validator - Input Validation
 * StudyFlow - Student Self-Teaching App
 *
 * Validates incoming data against a declarative set of rules. Supports
 * built-in rules (required, email, min/max, etc.), custom error messages,
 * and a unique-in-collection rule backed by FileStorage.
 *
 * Usage:
 *   $v = new Validator($data, [
 *       'email'    => 'required|email|unique:users,email',
 *       'password' => 'required|password',
 *       'name'     => 'required|string|min:2|max:50',
 *   ]);
 *
 *   if ($v->fails()) {
 *       $errors = $v->errors();
 *   }
 *
 *   $clean = $v->validated();
 * ============================================================================
 */

class Validator
{
    /** @var array Raw input data */
    private array $data;

    /** @var array Rule definitions keyed by field */
    private array $rules;

    /** @var array Custom error messages keyed by "field.rule" */
    private array $messages;

    /** @var array<string, string[]> Collected error messages keyed by field */
    private array $errors = [];

    /** @var bool Whether validation has been run */
    private bool $validated = false;

    /** @var FileStorage|null Optional storage for "unique" rule */
    private ?FileStorage $storage;

    // -------------------------------------------------------------------------
    // Construction & Public API
    // -------------------------------------------------------------------------

    /**
     * @param array           $data     Input data to validate
     * @param array           $rules    Validation rules per field
     * @param array           $messages Custom error messages (optional)
     * @param FileStorage|null $storage  FileStorage instance for "unique" rule
     */
    public function __construct(
        array $data,
        array $rules,
        array $messages = [],
        ?FileStorage $storage = null
    ) {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->storage  = $storage;

        $this->run();
    }

    /**
     * Static factory for fluent usage.
     *
     * @return static
     */
    public static function validate(
        array $data,
        array $rules,
        array $messages = [],
        ?FileStorage $storage = null
    ): static {
        return new static($data, $rules, $messages, $storage);
    }

    /**
     * Whether validation failed.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Whether validation passed.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Return all error messages.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Return the first error for a given field.
     *
     * @param string $field
     * @return string|null
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Return only the fields that had validation rules and passed.
     *
     * @return array
     */
    public function validated(): array
    {
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            if (!isset($this->errors[$field]) && array_key_exists($field, $this->data)) {
                $result[$field] = $this->data[$field];
            }
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Validation Engine
    // -------------------------------------------------------------------------

    /**
     * Execute all validation rules.
     */
    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);

            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        $this->validated = true;
    }

    /**
     * Apply a single rule to a field.
     *
     * @param string $field
     * @param string $rule  e.g. "required", "min:3", "in:a,b,c"
     */
    private function applyRule(string $field, string $rule): void
    {
        // Parse rule name and parameter
        $param = null;
        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule, 2);
        }

        $value  = $this->data[$field] ?? null;
        $exists = array_key_exists($field, $this->data);

        // If not required and value is empty, skip other rules
        if ($rule !== 'required' && !$this->isFilled($value)) {
            return;
        }

        $passed = match ($rule) {
            'required'     => $this->validateRequired($value, $exists),
            'email'        => $this->validateEmail($value),
            'min'          => $this->validateMin($value, (int) $param),
            'max'          => $this->validateMax($value, (int) $param),
            'string'       => $this->validateString($value),
            'numeric'      => $this->validateNumeric($value),
            'alpha'        => $this->validateAlpha($value),
            'alphanumeric' => $this->validateAlphanumeric($value),
            'url'          => $this->validateUrl($value),
            'date'         => $this->validateDate($value),
            'in'           => $this->validateIn($value, $param),
            'match'        => $this->validateMatch($value, $param),
            'unique'       => $this->validateUnique($value, $param, $field),
            'regex'        => $this->validateRegex($value, $param),
            'password'     => $this->validatePassword($value),
            'boolean'      => $this->validateBoolean($value),
            'array'        => $this->validateArray($value),
            'integer'      => $this->validateInteger($value),
            'json'         => $this->validateJson($value),
            'slug'         => $this->validateSlug($value),
            'not_in'       => $this->validateNotIn($value, $param),
            'between'      => $this->validateBetween($value, $param),
            'confirmed'    => $this->validateMatch($value, $field . '_confirmation'),
            default        => true, // Unknown rules pass
        };

        if (!$passed) {
            $this->addError($field, $rule, $param);
        }
    }

    // -------------------------------------------------------------------------
    // Individual Rule Validators
    // -------------------------------------------------------------------------

    private function validateRequired(mixed $value, bool $exists): bool
    {
        if (!$exists) {
            return false;
        }
        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

    private function validateEmail(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin(mixed $value, int $min): bool
    {
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') >= $min;
        }
        if (is_numeric($value)) {
            return (float) $value >= $min;
        }
        if (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    private function validateMax(mixed $value, int $max): bool
    {
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') <= $max;
        }
        if (is_numeric($value)) {
            return (float) $value <= $max;
        }
        if (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    private function validateString(mixed $value): bool
    {
        return is_string($value);
    }

    private function validateNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    private function validateAlpha(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[\pL\pM]+$/u', $value) === 1;
    }

    private function validateAlphanumeric(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[\pL\pM\pN]+$/u', $value) === 1;
    }

    private function validateUrl(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return strtotime($value) !== false;
    }

    /**
     * Value must be one of a comma-separated list.
     */
    private function validateIn(mixed $value, ?string $param): bool
    {
        if ($param === null) {
            return false;
        }
        $allowed = array_map('trim', explode(',', $param));
        return in_array((string) $value, $allowed, true);
    }

    /**
     * Value must NOT be in a comma-separated list.
     */
    private function validateNotIn(mixed $value, ?string $param): bool
    {
        if ($param === null) {
            return true;
        }
        $disallowed = array_map('trim', explode(',', $param));
        return !in_array((string) $value, $disallowed, true);
    }

    /**
     * Value must match another field.
     */
    private function validateMatch(mixed $value, ?string $otherField): bool
    {
        if ($otherField === null) {
            return false;
        }
        return isset($this->data[$otherField]) && $value === $this->data[$otherField];
    }

    /**
     * Value must be unique in a FileStorage collection.
     * Param format: "collection,field" or "collection,field,ignoreId"
     */
    private function validateUnique(mixed $value, ?string $param, string $field): bool
    {
        if ($param === null || $this->storage === null) {
            return true; // Cannot validate without storage
        }

        $parts      = explode(',', $param);
        $collection = $parts[0];
        $checkField = $parts[1] ?? $field;
        $ignoreId   = $parts[2] ?? null;

        $items = $this->storage->query($collection, function (array $item) use ($checkField, $value, $ignoreId) {
            if ($ignoreId !== null && isset($item['_id']) && $item['_id'] === $ignoreId) {
                return false;
            }
            return isset($item[$checkField]) && $item[$checkField] === $value;
        });

        return empty($items);
    }

    /**
     * Value must match a regular expression.
     */
    private function validateRegex(mixed $value, ?string $pattern): bool
    {
        if (!is_string($value) || $pattern === null) {
            return false;
        }
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Password rule: minimum 8 chars, at least one uppercase,
     * one lowercase, and one digit.
     */
    private function validatePassword(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (mb_strlen($value, 'UTF-8') < 8) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $value)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $value)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $value)) {
            return false;
        }
        return true;
    }

    private function validateBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    private function validateArray(mixed $value): bool
    {
        return is_array($value);
    }

    private function validateInteger(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateSlug(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }

    /**
     * Numeric value must be between two values (inclusive).
     * Param format: "min,max"
     */
    private function validateBetween(mixed $value, ?string $param): bool
    {
        if ($param === null) {
            return false;
        }
        $parts = explode(',', $param);
        if (count($parts) !== 2) {
            return false;
        }
        $min = (float) $parts[0];
        $max = (float) $parts[1];

        if (is_string($value)) {
            $len = mb_strlen($value, 'UTF-8');
            return $len >= $min && $len <= $max;
        }
        if (is_numeric($value)) {
            $num = (float) $value;
            return $num >= $min && $num <= $max;
        }
        if (is_array($value)) {
            $cnt = count($value);
            return $cnt >= $min && $cnt <= $max;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Error Handling
    // -------------------------------------------------------------------------

    /**
     * Add an error message for a field/rule combination.
     */
    private function addError(string $field, string $rule, ?string $param): void
    {
        // Check for a custom message first
        $customKey = "{$field}.{$rule}";
        if (isset($this->messages[$customKey])) {
            $this->errors[$field][] = $this->messages[$customKey];
            return;
        }

        // Fall back to a default message
        $label = $this->humanize($field);
        $this->errors[$field][] = $this->defaultMessage($label, $rule, $param);
    }

    /**
     * Build a human-readable default error message.
     */
    private function defaultMessage(string $label, string $rule, ?string $param): string
    {
        return match ($rule) {
            'required'     => "{$label} is required.",
            'email'        => "{$label} must be a valid email address.",
            'min'          => "{$label} must be at least {$param} characters.",
            'max'          => "{$label} must not exceed {$param} characters.",
            'string'       => "{$label} must be a string.",
            'numeric'      => "{$label} must be a number.",
            'alpha'        => "{$label} must contain only letters.",
            'alphanumeric' => "{$label} must contain only letters and numbers.",
            'url'          => "{$label} must be a valid URL.",
            'date'         => "{$label} must be a valid date.",
            'in'           => "{$label} must be one of: {$param}.",
            'not_in'       => "{$label} must not be one of: {$param}.",
            'match'        => "{$label} does not match the {$param} field.",
            'confirmed'    => "{$label} confirmation does not match.",
            'unique'       => "{$label} has already been taken.",
            'regex'        => "{$label} format is invalid.",
            'password'     => "{$label} must be at least 8 characters with uppercase, lowercase, and a digit.",
            'boolean'      => "{$label} must be true or false.",
            'array'        => "{$label} must be an array.",
            'integer'      => "{$label} must be an integer.",
            'json'         => "{$label} must be valid JSON.",
            'slug'         => "{$label} must be a valid URL slug.",
            'between'      => "{$label} must be between {$param}.",
            default        => "{$label} is invalid.",
        };
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether a value is considered "filled" (non-empty).
     */
    private function isFilled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        if (is_array($value) && count($value) === 0) {
            return false;
        }
        return true;
    }

    /**
     * Convert a snake_case or camelCase field name to a human-readable label.
     */
    private function humanize(string $field): string
    {
        $label = str_replace(['_', '-'], ' ', $field);
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);
        return ucfirst(mb_strtolower($label, 'UTF-8'));
    }
}
