<?php

namespace App\Services;

use Hashids\Hashids;

class IdHasher
{
    private $hashids;
    private $id;
    private $result;
    private $isHash;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->hashids = new Hashids(
            salt: config('hashids.salt'),
            minHashLength: config('hashids.min_length'),
            alphabet: config('hashids.alphabet')
        );
        $this->isHash = $this->isLikelyHash($id);
        $this->process();
    }

    private function isLikelyHash(string $id): bool
    {
        $alphabet = config('hashids.alphabet');
        $minLength = config('hashids.min_length');
        $isHash = strlen($id) >= $minLength && preg_match("/^[$alphabet]+$/", $id);
        $isOriginalId = preg_match("/^tt[0-9]{7}$/", $id);
        return $isHash && !$isOriginalId;
    }

    private function stringToNumber(string $str): int
    {
        $alphabet = config('hashids.alphabet');
        $base = strlen($alphabet);
        $number = 0;

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            $value = strpos($alphabet, $char);
            if ($value === false) {
                throw new \InvalidArgumentException("Invalid character in ID: {$char}");
            }
            $number = $number * $base + $value;
        }

        return $number;
    }

    private function numberToString(int $number): string
    {
        $alphabet = config('hashids.alphabet');
        $base = strlen($alphabet);
        $str = '';

        while ($number > 0) {
            $remainder = $number % $base;
            $str = $alphabet[$remainder] . $str;
            $number = (int)($number / $base);
        }

        return str_pad($str, 9, '0', STR_PAD_LEFT);
    }

    private function process(): void
    {
        if ($this->isHash) {
            $decoded = $this->hashids->decode($this->id);
            if (empty($decoded)) {
                throw new \InvalidArgumentException('Invalid hashed ID');
            }
            $this->result = $this->numberToString($decoded[0]);
        } else {
            $number = $this->stringToNumber($this->id);
            $this->result = $this->hashids->encode($number);
        }
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function isResultHash(): bool
    {
        return $this->isHash;
    }
}
