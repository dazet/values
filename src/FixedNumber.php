<?php

namespace GW\Value;

final class FixedNumber implements NumberValue
{
    public const DEFAULT_SCALE = 2;

    /** @var int */
    private $digits;

    /** @var int */
    private $scale;

    /** @var int */
    private $roundMode;

    public function __construct(int $digits, int $scale, ?int $roundMode = null)
    {
        $this->digits = $digits;
        $this->scale = $scale;
        $this->roundMode = $roundMode ?? PHP_ROUND_HALF_UP;
    }

    /**
     * @param int|float|string $number
     */
    public static function from($number, ?int $scale = null, ?int $roundMode = null): self
    {
        if (\is_int($number)) {
            return self::fromInt($number, $scale, $roundMode);
        }

        if (\is_float($number)) {
            return self::fromFloat($number, $scale, $roundMode);
        }

        if (\is_string($number)) {
            return self::fromString($number, $scale, $roundMode);
        }

        throw new \InvalidArgumentException(\sprintf('Value `%s` is not a valid number', $number));
    }

    public static function fromInt(int $number, ?int $scale = null, ?int $roundMode = null): self
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        $digits = $number * (int)(10 ** $scale);

        return new self($digits, $scale, $roundMode);
    }

    public static function fromFloat(float $number, ?int $scale = null, ?int $roundMode = null): self
    {
        $scale = $scale ?? self::DEFAULT_SCALE;
        $roundMode = $roundMode ?? PHP_ROUND_HALF_UP;
        $digits = self::roundDigits($number, $scale, $roundMode);

        return new self($digits, $scale, $roundMode);
    }

    public static function fromString(string $input, ?int $scale = null, ?int $roundMode = null): self
    {
        // strip formatting
        $number = Wrap::string($input)->replacePattern('/[\s,]/', '');
        if (!\is_numeric($number->toString())) {
            throw new \InvalidArgumentException("String `{$number}` is not a valid number");
        }

        // guess scale
        $dotPosition = $number->position('.');
        $realScale = $dotPosition !== null ? $number->length() - ($dotPosition + 1) : 0;
        $scale = $scale ?? $realScale;

        return self::fromFloat((float)$number->toString(), $scale ?? $realScale, $roundMode);
    }

    public function withScale(int $scale): self
    {
        if ($scale === $this->scale) {
            return $this;
        }

        $shift = 10 ** ($scale - $this->scale);
        $digits = (int)\round($this->digits * $shift, 0, $this->roundMode);

        return new self($digits, $scale, $this->roundMode);
    }

    public function withRoundMode(int $roundMode): self
    {
        if ($roundMode === $this->roundMode) {
            return $this;
        }

        return new self($this->digits, $this->scale, $roundMode);
    }

    public function withDigits(int $digits): self
    {
        if ($digits === $this->digits) {
            return $this;
        }

        return new self($digits, $this->scale, $this->roundMode);
    }

    /**
     * @return int Number of decimal places, ie. 1234.12 has scale = 2
     */
    public function scale(): int
    {
        return $this->scale;
    }

    public function isInteger(): bool
    {
        return $this->scale <= 0;
    }

    public function isDecimal(): bool
    {
        return $this->scale > 0;
    }

    public function toInt(): int
    {
        return (int)$this->toFloat();
    }

    public function toFloat(): float
    {
        return (float)($this->digits / $this->pointShift());
    }

    public function toString(): string
    {
        if ($this->scale === 0) {
            return (string)$this->digits;
        }

        if ($this->scale < 0) {
            return (string)(int)($this->digits / $this->pointShift());
        }

        return \number_format($this->toFloat(), $this->scale, '.', '');
    }

    public function toStringValue(): StringValue
    {
        return Wrap::string($this->toString());
    }

    public function format(int $scale = 0, string $point = '.', string $thousandsSeparator = ','): StringValue
    {
        return Wrap::string(\number_format($this->toFloat(), $scale, $point, $thousandsSeparator));
    }

    /**
     * @return int {-1, 0, 1}
     */
    public function compare(NumberValue $other): int
    {
        return $this->digits <=> $this->alignNumber($other)->digits;
    }

    public function equals(NumberValue $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function greaterThan(NumberValue $other): bool
    {
        return $this->compare($other) === 1;
    }

    public function lesserThan(NumberValue $other): bool
    {
        return $this->compare($other) === -1;
    }

    public function add(NumberValue $other): self
    {
        return $this->withDigits($this->digits + $this->alignNumber($other)->digits);
    }

    public function subtract(NumberValue $other): self
    {
        return $this->withDigits($this->digits - $this->alignNumber($other)->digits);
    }

    public function multiply(NumberValue $other): self
    {
        return $this->withFloatValue($this->toFloat() * $other->toFloat());
    }

    public function divide(NumberValue $other): self
    {
        return $this->withFloatValue($this->toFloat() / $other->toFloat());
    }

    public function abs(): self
    {
        return $this->withDigits(\abs($this->digits));
    }

    public function round(int $scale = 0, ?int $roundMode = null): self
    {
        if ($scale >= $this->scale) {
            return $this;
        }

        $float = \round($this->toFloat(), $scale, $this->roundMode);

        return $this->withFloatValue($float);
    }

    public function floor(int $scale = 0): self
    {
        if ($scale >= $this->scale) {
            return $this;
        }

        $shift = 10 ** $scale;
        $float = \floor($this->toFloat() * $shift) / $shift;

        return $this->withFloatValue($float);
    }

    public function ceil(int $scale = 0): self
    {
        if ($scale >= $this->scale) {
            return $this;
        }

        $shift = 10 ** $scale;
        $float = \ceil($this->toFloat() * $shift) / $shift;

        return $this->withFloatValue($float);
    }

    /**
     * @return bool false when 0, true otherwise
     */
    public function isEmpty(): bool
    {
        return $this->equals(self::fromInt(0));
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function roundDigits(float $number, int $scale, int $roundMode): int
    {
        return (int)\round($number * (10 ** $scale), 0, $roundMode);
    }

    private function alignNumber(NumberValue $other): FixedNumber
    {
        if ($other instanceof self) {
            return $other->withScale($this->scale);
        }

        return self::fromFloat($other->toFloat(), $this->scale, $this->roundMode);
    }

    private function withFloatValue(float $value): self
    {
        return $this->withDigits(self::roundDigits($value, $this->scale, $this->roundMode));
    }

    /**
     * @return int|float
     */
    private function pointShift()
    {
        return 10 ** $this->scale;
    }
}