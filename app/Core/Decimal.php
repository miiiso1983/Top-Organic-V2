<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Decimal arithmetic wrapper around PHP bcmath.
 * Immutable value object with a fixed default scale.
 */
class Decimal
{
    private const DEFAULT_SCALE = 8;

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(float|int|string $value): self
    {
        return new self(self::normalize((string) $value));
    }

    public function add(float|int|string|self $other): self
    {
        return new self(bcadd($this->value, self::unwrap($other), self::DEFAULT_SCALE));
    }

    public function sub(float|int|string|self $other): self
    {
        return new self(bcsub($this->value, self::unwrap($other), self::DEFAULT_SCALE));
    }

    public function mul(float|int|string|self $other): self
    {
        return new self(bcmul($this->value, self::unwrap($other), self::DEFAULT_SCALE));
    }

    public function div(float|int|string|self $other): self
    {
        $divisor = self::unwrap($other);
        if (bccomp($divisor, '0', self::DEFAULT_SCALE) === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return new self(bcdiv($this->value, $divisor, self::DEFAULT_SCALE));
    }

    public function pow(int $exponent): self
    {
        return new self(bcpow($this->value, (string) $exponent, self::DEFAULT_SCALE));
    }

    public function abs(): self
    {
        return new self(ltrim($this->value, '-'));
    }

    public function round(int $scale): self
    {
        return new self(self::bcround($this->value, $scale));
    }

    public function compareTo(float|int|string|self $other): int
    {
        return bccomp($this->value, self::unwrap($other), self::DEFAULT_SCALE);
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', self::DEFAULT_SCALE) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', self::DEFAULT_SCALE) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', self::DEFAULT_SCALE) < 0;
    }

    public function toString(int $scale = self::DEFAULT_SCALE): string
    {
        $rounded = self::bcround($this->value, $scale);
        if (str_contains($rounded, '.')) {
            $rounded = rtrim(rtrim($rounded, '0'), '.');
        }
        return $rounded;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function toDb(): string
    {
        return $this->value;
    }

    private static function unwrap(float|int|string|self $value): string
    {
        if ($value instanceof self) {
            return $value->value;
        }
        return self::normalize((string) $value);
    }

    private static function bcround(string $value, int $scale): string
    {
        if ($scale < 0) {
            return '0';
        }
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }
        if (!str_contains($value, '.')) {
            $value .= '.';
        }
        [$intPart, $fracPart] = explode('.', $value, 2);
        $fracPart = str_pad($fracPart, $scale + 1, '0', STR_PAD_RIGHT);
        $roundDigit = (int) ($fracPart[$scale] ?? '0');
        $fracPart = substr($fracPart, 0, $scale);
        if ($roundDigit >= 5) {
            $increment = '0.' . str_repeat('0', max(0, $scale - 1)) . '1';
            $intPart = bcadd($intPart . '.' . $fracPart, $increment, $scale);
        } else {
            $intPart = $intPart . '.' . $fracPart;
        }
        return ($negative ? '-' : '') . rtrim(rtrim($intPart, '0'), '.');
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'nan') {
            return '0';
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Invalid decimal value: {$value}");
        }
        return $value;
    }
}
