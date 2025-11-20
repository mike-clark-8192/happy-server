<?php

namespace Happy\Utils;

/**
 * Input validation utility for API requests.
 */
class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Create a new validator instance.
     */
    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * Require a field to be present and non-empty.
     */
    public function required(string $field, ?string $message = null): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            $this->errors[$field] = $message ?? "$field is required";
        }
        return $this;
    }

    /**
     * Validate field is a string.
     */
    public function string(string $field, ?string $message = null): self
    {
        if (isset($this->data[$field]) && !is_string($this->data[$field])) {
            $this->errors[$field] = $message ?? "$field must be a string";
        }
        return $this;
    }

    /**
     * Validate field is an integer.
     */
    public function integer(string $field, ?string $message = null): self
    {
        if (isset($this->data[$field]) && !is_int($this->data[$field])) {
            $this->errors[$field] = $message ?? "$field must be an integer";
        }
        return $this;
    }

    /**
     * Validate field is an array.
     */
    public function array(string $field, ?string $message = null): self
    {
        if (isset($this->data[$field]) && !is_array($this->data[$field])) {
            $this->errors[$field] = $message ?? "$field must be an array";
        }
        return $this;
    }

    /**
     * Validate field is a boolean.
     */
    public function boolean(string $field, ?string $message = null): self
    {
        if (isset($this->data[$field]) && !is_bool($this->data[$field])) {
            $this->errors[$field] = $message ?? "$field must be a boolean";
        }
        return $this;
    }

    /**
     * Validate string length.
     */
    public function maxLength(string $field, int $max, ?string $message = null): self
    {
        if (isset($this->data[$field]) && is_string($this->data[$field])) {
            if (strlen($this->data[$field]) > $max) {
                $this->errors[$field] = $message ?? "$field must be at most $max characters";
            }
        }
        return $this;
    }

    /**
     * Validate minimum string length.
     */
    public function minLength(string $field, int $min, ?string $message = null): self
    {
        if (isset($this->data[$field]) && is_string($this->data[$field])) {
            if (strlen($this->data[$field]) < $min) {
                $this->errors[$field] = $message ?? "$field must be at least $min characters";
            }
        }
        return $this;
    }

    /**
     * Validate numeric range.
     */
    public function range(string $field, int $min, int $max, ?string $message = null): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            $value = $this->data[$field];
            if ($value < $min || $value > $max) {
                $this->errors[$field] = $message ?? "$field must be between $min and $max";
            }
        }
        return $this;
    }

    /**
     * Validate field matches a regex pattern.
     */
    public function pattern(string $field, string $pattern, ?string $message = null): self
    {
        if (isset($this->data[$field]) && is_string($this->data[$field])) {
            if (!preg_match($pattern, $this->data[$field])) {
                $this->errors[$field] = $message ?? "$field has invalid format";
            }
        }
        return $this;
    }

    /**
     * Validate field is a valid email.
     */
    public function email(string $field, ?string $message = null): self
    {
        if (isset($this->data[$field]) && is_string($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = $message ?? "$field must be a valid email";
            }
        }
        return $this;
    }

    /**
     * Validate field is a valid URL.
     */
    public function url(string $field, ?string $message = null): self
    {
        if (isset($this->data[$field]) && is_string($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->errors[$field] = $message ?? "$field must be a valid URL";
            }
        }
        return $this;
    }

    /**
     * Validate field is in a list of allowed values.
     */
    public function in(string $field, array $allowed, ?string $message = null): self
    {
        if (isset($this->data[$field])) {
            if (!in_array($this->data[$field], $allowed, true)) {
                $this->errors[$field] = $message ?? "$field must be one of: " . implode(', ', $allowed);
            }
        }
        return $this;
    }

    /**
     * Check if validation passed.
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed.
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get validation errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message.
     */
    public function firstError(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }

    /**
     * Throw exception if validation fails.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        if ($this->fails()) {
            throw new ValidationException($this->firstError(), $this->errors);
        }
    }
}

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(string $message, array $errors)
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
