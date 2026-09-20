<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Validator + sanitizer sederhana berbasis aturan string.
 * Contoh aturan: 'required|string|max:100', 'numeric|min:0', 'date', 'email'.
 */
final class Validator
{
    private array $errors = [];
    private array $valid = [];

    public function __construct(private array $data) {}

    public static function make(array $data): self
    {
        return new self($data);
    }

    /** @param array<string,string> $rules kolom => 'rule1|rule2:arg' */
    public function validate(array $rules): bool
    {
        foreach ($rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            $rulesList = explode('|', $ruleStr);
            $isRequired = in_array('required', $rulesList, true);

            if (!$isRequired && ($value === null || $value === '')) {
                $this->valid[$field] = null;
                continue;
            }

            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = $this->applyRule($name, $value, $arg, $field);
                if (!$ok) {
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->valid[$field] = $this->cast($rulesList, $value);
            }
        }
        return empty($this->errors);
    }

    private function applyRule(string $name, mixed $value, ?string $arg, string $field): bool
    {
        switch ($name) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                    return $this->fail($field, 'wajib diisi');
                }
                return true;
            case 'string':
                if (!is_string($value)) { return $this->fail($field, 'harus teks'); }
                return true;
            case 'numeric':
                if (!is_numeric($value)) { return $this->fail($field, 'harus angka'); }
                return true;
            case 'int':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    return $this->fail($field, 'harus bilangan bulat');
                }
                return true;
            case 'min':
                if (is_numeric($value) ? ((float) $value < (float) $arg) : (mb_strlen((string) $value) < (int) $arg)) {
                    return $this->fail($field, "minimal $arg");
                }
                return true;
            case 'max':
                if (is_numeric($value) ? ((float) $value > (float) $arg) : (mb_strlen((string) $value) > (int) $arg)) {
                    return $this->fail($field, "maksimal $arg");
                }
                return true;
            case 'gt':
                if ((float) $value <= (float) $arg) { return $this->fail($field, "harus lebih dari $arg"); }
                return true;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { return $this->fail($field, 'email tidak valid'); }
                return true;
            case 'date':
                if (strtotime((string) $value) === false) { return $this->fail($field, 'tanggal tidak valid'); }
                return true;
            case 'not_future':
                if (strtotime((string) $value) > time()) { return $this->fail($field, 'tidak boleh di masa depan'); }
                return true;
            case 'in':
                $opts = explode(',', (string) $arg);
                if (!in_array((string) $value, $opts, true)) { return $this->fail($field, 'pilihan tidak valid'); }
                return true;
            default:
                return true;
        }
    }

    private function cast(array $rules, mixed $value): mixed
    {
        if (in_array('int', $rules, true)) { return (int) $value; }
        if (in_array('numeric', $rules, true)) { return (float) $value; }
        if (is_string($value)) { return trim($value); }
        return $value;
    }

    private function fail(string $field, string $msg): bool
    {
        $this->errors[$field] ??= ucfirst(str_replace('_', ' ', $field)) . ' ' . $msg;
        return false;
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function firstError(): ?string { return $this->errors ? reset($this->errors) : null; }
    public function validated(): array { return $this->valid; }

    /** Sanitasi string bebas: strip tag berbahaya, trim. Untuk cegah XSS pada penyimpanan. */
    public static function clean(?string $s): string
    {
        return trim(strip_tags((string) $s));
    }
}
