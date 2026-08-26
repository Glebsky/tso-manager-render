<?php

declare(strict_types=1);

namespace App\Services\Amf;

/**
 * AMF3 Encoder for TSO protocol serialization.
 */
final class Amf3Encoder
{
    private string $out = '';

    public function encode(mixed $data): void
    {
        if (is_null($data)) {
            $this->out .= chr(0x01);
        } elseif (is_bool($data)) {
            $this->out .= $data ? chr(0x03) : chr(0x02);
        } elseif (is_int($data)) {
            if ($data >= -268435456 && $data <= 268435455) {
                $this->out .= chr(0x04);
                $this->writeU29($data);
            } else {
                $this->out .= chr(0x05);
                $this->writeDouble((float) $data);
            }
        } elseif (is_float($data)) {
            $this->out .= chr(0x05);
            $this->writeDouble($data);
        } elseif (is_string($data)) {
            $this->out .= chr(0x06);
            $this->writeString($data);
        } elseif (is_array($data)) {
            if (! array_is_list($data)) {
                // Associative array -> dynamic object
                $this->out .= chr(0x0A);
                $this->out .= chr(0x0B);
                $this->writeString('');
                foreach ($data as $k => $v) {
                    $this->writeString((string) $k);
                    $this->encode($v);
                }
                $this->writeString('');
            } else {
                // Strict array
                $this->out .= chr(0x09);
                $this->writeU29((count($data) << 1) | 1);
                $this->writeString('');
                foreach ($data as $v) {
                    $this->encode($v);
                }
            }
        } elseif (is_object($data)) {
            $this->out .= chr(0x0A);
            $className = get_class($data);
            if ($className === 'stdClass') {
                $className = '';
            } elseif (str_contains($className, '\\')) {
                $className = substr($className, strrpos($className, '\\') + 1);
            }

            if (str_starts_with($className, 'defaultGame_') || str_starts_with($className, 'Communication_') || str_starts_with($className, 'flex_messaging_')) {
                $className = str_replace('_', '.', $className);
            }

            $props = get_object_vars($data);
            $propCount = count($props);

            $this->writeU29(($propCount << 4) | 0x03);
            $this->writeString($className);

            foreach ($props as $k => $v) {
                $this->writeString((string) $k);
            }
            foreach ($props as $v) {
                $this->encode($v);
            }
        }
    }

    private function writeU29(int $value): void
    {
        $value &= 0x1FFFFFFF;
        if ($value < 0x80) {
            $this->out .= chr($value);
        } elseif ($value < 0x4000) {
            $this->out .= chr(($value >> 7 & 0x7F) | 0x80).chr($value & 0x7F);
        } elseif ($value < 0x200000) {
            $this->out .= chr(($value >> 14 & 0x7F) | 0x80).chr(($value >> 7 & 0x7F) | 0x80).chr($value & 0x7F);
        } else {
            $this->out .= chr(($value >> 22 & 0x7F) | 0x80).chr(($value >> 15 & 0x7F) | 0x80).chr(($value >> 8 & 0x7F) | 0x80).chr($value & 0xFF);
        }
    }

    private function writeDouble(float $value): void
    {
        $this->out .= strrev(pack('d', $value));
    }

    private function writeString(string $value): void
    {
        if ($value === '') {
            $this->out .= chr(0x01);

            return;
        }
        $len = strlen($value);
        $this->writeU29(($len << 1) | 1);
        $this->out .= $value;
    }

    public function getOutput(): string
    {
        return $this->out;
    }
}
