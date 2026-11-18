<?php
/**
 * JOI Data Validator for PHP
 * Meniru API dan perilaku Joi (JavaScript) untuk validasi data terstruktur yang deklaratif dan ekspresif.
 */

namespace Joi;

class ValidationError extends \Exception {
    protected array $errors = [];

    public function __construct(string $message, array $errors = [], int $code = 400) {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}

class ValidationResult {
    public bool $isValid;
    public ?string $error;
    public array $errors;
    public mixed $value;

    public function __construct(bool $isValid, mixed $value = null, ?string $error = null, array $errors = []) {
        $this->isValid = $isValid;
        $this->value = $value;
        $this->error = $error;
        $this->errors = $errors;
    }
}

abstract class Schema {
    protected bool $isRequired = false;
    protected ?string $requiredMsg = null;
    protected mixed $defaultValue = null;
    protected bool $hasDefault = false;
    protected array $customMessages = [];

    public function required(?string $message = null): static {
        $this->isRequired = true;
        $this->requiredMsg = $message;
        return $this;
    }

    public function optional(): static {
        $this->isRequired = false;
        return $this;
    }

    public function default(mixed $value): static {
        $this->hasDefault = true;
        $this->defaultValue = $value;
        return $this;
    }

    public function messages(array $messages): static {
        $this->customMessages = array_merge($this->customMessages, $messages);
        return $this;
    }

    abstract public function validate(mixed $value, string $field = 'field'): ValidationResult;
}

class StringSchema extends Schema {
    protected ?int $min = null;
    protected ?string $minMsg = null;
    protected ?int $max = null;
    protected ?string $maxMsg = null;
    protected bool $doTrim = true;
    protected bool $alphanumOnly = false;
    protected ?string $alphanumMsg = null;
    protected ?string $regex = null;
    protected ?string $regexMsg = null;

    public function trim(bool $enable = true): static {
        $this->doTrim = $enable;
        return $this;
    }

    public function min(int $length, ?string $message = null): static {
        $this->min = $length;
        $this->minMsg = $message;
        return $this;
    }

    public function max(int $length, ?string $message = null): static {
        $this->max = $length;
        $this->maxMsg = $message;
        return $this;
    }

    public function alphanum(?string $message = null): static {
        $this->alphanumOnly = true;
        $this->alphanumMsg = $message;
        return $this;
    }

    public function pattern(string $regex, ?string $message = null): static {
        $this->regex = $regex;
        $this->regexMsg = $message;
        return $this;
    }

    public function validate(mixed $value, string $field = 'field'): ValidationResult {
        $errors = [];

        if ($value === null || $value === '') {
            if ($this->hasDefault) {
                return new ValidationResult(true, $this->defaultValue);
            }
            if ($this->isRequired) {
                $msg = $this->requiredMsg ?? ($this->customMessages['any.required'] ?? "{$field} wajib diisi");
                return new ValidationResult(false, null, $msg, [$msg]);
            }
            return new ValidationResult(true, '');
        }

        $str = (string)$value;
        if ($this->doTrim) {
            $str = trim($str);
        }

        if ($this->isRequired && $str === '') {
            $msg = $this->requiredMsg ?? ($this->customMessages['any.required'] ?? "{$field} tidak boleh kosong");
            return new ValidationResult(false, null, $msg, [$msg]);
        }

        if ($this->min !== null && mb_strlen($str) < $this->min) {
            $msg = $this->minMsg ?? ($this->customMessages['string.min'] ?? "{$field} minimal {$this->min} karakter");
            $errors[] = $msg;
        }

        if ($this->max !== null && mb_strlen($str) > $this->max) {
            $msg = $this->maxMsg ?? ($this->customMessages['string.max'] ?? "{$field} maksimal {$this->max} karakter");
            $errors[] = $msg;
        }

        if ($this->alphanumOnly && !preg_match('/^[a-zA-Z0-9_]+$/', $str)) {
            $msg = $this->alphanumMsg ?? ($this->customMessages['string.alphanum'] ?? "{$field} hanya boleh berisi huruf, angka, dan underscore");
            $errors[] = $msg;
        }

        if ($this->regex !== null && !preg_match($this->regex, $str)) {
            $msg = $this->regexMsg ?? ($this->customMessages['string.pattern'] ?? "{$field} format tidak sesuai");
            $errors[] = $msg;
        }

        if (!empty($errors)) {
            return new ValidationResult(false, null, $errors[0], $errors);
        }

        return new ValidationResult(true, $str);
    }
}

class NumberSchema extends Schema {
    protected bool $isInteger = false;
    protected ?float $min = null;
    protected ?string $minMsg = null;
    protected ?float $max = null;
    protected ?string $maxMsg = null;
    protected bool $mustBePositive = false;
    protected ?string $positiveMsg = null;

    public function integer(?string $message = null): static {
        $this->isInteger = true;
        if ($message) $this->customMessages['number.integer'] = $message;
        return $this;
    }

    public function min(float $min, ?string $message = null): static {
        $this->min = $min;
        $this->minMsg = $message;
        return $this;
    }

    public function max(float $max, ?string $message = null): static {
        $this->max = $max;
        $this->maxMsg = $message;
        return $this;
    }

    public function positive(?string $message = null): static {
        $this->mustBePositive = true;
        $this->positiveMsg = $message;
        return $this;
    }

    public function validate(mixed $value, string $field = 'field'): ValidationResult {
        $errors = [];

        if ($value === null || $value === '') {
            if ($this->hasDefault) {
                return new ValidationResult(true, $this->defaultValue);
            }
            if ($this->isRequired) {
                $msg = $this->requiredMsg ?? ($this->customMessages['any.required'] ?? "{$field} wajib diisi");
                return new ValidationResult(false, null, $msg, [$msg]);
            }
            return new ValidationResult(true, null);
        }

        if (!is_numeric($value)) {
            $msg = $this->customMessages['number.base'] ?? "{$field} harus berupa angka";
            return new ValidationResult(false, null, $msg, [$msg]);
        }

        $num = $this->isInteger ? (int)$value : (float)$value;

        if ($this->mustBePositive && $num <= 0) {
            $msg = $this->positiveMsg ?? ($this->customMessages['number.positive'] ?? "{$field} harus lebih besar dari 0");
            $errors[] = $msg;
        }

        if ($this->min !== null && $num < $this->min) {
            $msg = $this->minMsg ?? ($this->customMessages['number.min'] ?? "{$field} minimal bernilai {$this->min}");
            $errors[] = $msg;
        }

        if ($this->max !== null && $num > $this->max) {
            $msg = $this->maxMsg ?? ($this->customMessages['number.max'] ?? "{$field} maksimal bernilai {$this->max}");
            $errors[] = $msg;
        }

        if (!empty($errors)) {
            return new ValidationResult(false, null, $errors[0], $errors);
        }

        return new ValidationResult(true, $num);
    }
}

class InSchema extends Schema {
    protected array $allowedValues = [];
    protected ?string $inMsg = null;

    public function __construct(array $allowedValues, ?string $message = null) {
        $this->allowedValues = $allowedValues;
        $this->inMsg = $message;
    }

    public function validate(mixed $value, string $field = 'field'): ValidationResult {
        if ($value === null || $value === '') {
            if ($this->hasDefault) {
                return new ValidationResult(true, $this->defaultValue);
            }
            if ($this->isRequired) {
                $msg = $this->requiredMsg ?? "{$field} wajib dipilih";
                return new ValidationResult(false, null, $msg, [$msg]);
            }
            return new ValidationResult(true, null);
        }

        if (!in_array($value, $this->allowedValues, false)) {
            $allowedStr = implode(', ', $this->allowedValues);
            $msg = $this->inMsg ?? ($this->customMessages['any.only'] ?? "{$field} harus salah satu dari: {$allowedStr}");
            return new ValidationResult(false, null, $msg, [$msg]);
        }

        return new ValidationResult(true, $value);
    }
}

class ObjectSchema extends Schema {
    protected array $fields = [];
    protected bool $stripUnknown = true;

    public function __construct(array $fields = []) {
        $this->fields = $fields;
    }

    public function stripUnknown(bool $strip = true): static {
        $this->stripUnknown = $strip;
        return $this;
    }

    public function validate(mixed $value, string $field = 'data'): ValidationResult {
        if (!is_array($value)) {
            $msg = "{$field} harus berupa objek atau array asosiatif";
            return new ValidationResult(false, null, $msg, [$msg]);
        }

        $validatedData = [];
        $allErrors = [];

        foreach ($this->fields as $fieldName => $schema) {
            $fieldVal = $value[$fieldName] ?? null;
            $res = $schema->validate($fieldVal, $fieldName);

            if (!$res->isValid) {
                $allErrors = array_merge($allErrors, $res->errors);
            } else {
                $validatedData[$fieldName] = $res->value;
            }
        }

        if (!$this->stripUnknown) {
            foreach ($value as $k => $v) {
                if (!isset($this->fields[$k])) {
                    $validatedData[$k] = $v;
                }
            }
        }

        if (!empty($allErrors)) {
            return new ValidationResult(false, null, $allErrors[0], $allErrors);
        }

        return new ValidationResult(true, $validatedData);
    }
}

class Joi {
    public static function object(array $fields = []): ObjectSchema {
        return new ObjectSchema($fields);
    }

    public static function string(): StringSchema {
        return new StringSchema();
    }

    public static function number(): NumberSchema {
        return new NumberSchema();
    }

    public static function integer(): NumberSchema {
        return (new NumberSchema())->integer();
    }

    public static function in(array $allowedValues, ?string $message = null): InSchema {
        return new InSchema($allowedValues, $message);
    }

    /**
     * Memvalidasi input data terhadap schema Joi.
     * Mengembalikan data yang bersih atau melempar ValidationError jika tidak valid.
     */
    public static function validate(array $data, ObjectSchema $schema): array {
        $res = $schema->validate($data);
        if (!$res->isValid) {
            throw new ValidationError($res->error, $res->errors, 400);
        }
        return $res->value;
    }
}
