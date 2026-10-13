<?php

namespace Tests\Unit;

use App\Support\Dpid;
use PHPUnit\Framework\TestCase;

class DpidTest extends TestCase
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function test_generated_ids_are_valid_and_formatted(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $dpid = Dpid::generate();
            $this->assertMatchesRegularExpression('/^DP-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $dpid);
            $this->assertTrue(Dpid::isValid($dpid));
        }
    }

    public function test_every_single_character_typo_is_detected(): void
    {
        $raw = Dpid::normalize(Dpid::generate());

        for ($pos = 0; $pos < 8; $pos++) {
            foreach (str_split(self::ALPHABET) as $c) {
                if ($c === $raw[$pos]) {
                    continue;
                }
                $typo = substr_replace($raw, $c, $pos, 1);
                $this->assertFalse(Dpid::isValid($typo), "Salah ketik $typo tidak terdeteksi");
            }
        }
    }

    public function test_normalize_accepts_loose_typing(): void
    {
        $dpid = Dpid::generate();
        $loose = strtolower(str_replace('-', ' ', $dpid));

        $this->assertSame($dpid, Dpid::format(Dpid::normalize($loose)));
        $this->assertTrue(Dpid::isValid($loose));
        $this->assertFalse(Dpid::isValid('DP-1234'));
    }
}
