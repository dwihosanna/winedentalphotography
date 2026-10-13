<?php

namespace App\Support;

/** Garis bantu kamera per slot (resources/slots/slots.v2.json, hasil tools/gen_slots.py): overlay, orientasi, cermin. */
final class SlotGuides
{
    /** @var array{version: string, slots: list<array<string, mixed>>}|null */
    private static ?array $data = null;

    /** @return array{version: string, slots: list<array<string, mixed>>} */
    public static function all(): array
    {
        return self::$data ??= json_decode(file_get_contents(resource_path('slots/slots.v2.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function version(): string
    {
        return self::all()['version'];
    }

    /** @return array<string, mixed>|null */
    public static function for(string $slotCode): ?array
    {
        return collect(self::all()['slots'])->firstWhere('code', $slotCode);
    }

    /**
     * Slot yang biasanya dipotret lewat cermin dan otomatis dibalik (FR-14).
     *
     * @return array{axis: 'horizontal'|'vertical', default: bool}|null
     */
    public static function mirror(string $slotCode): ?array
    {
        return self::for($slotCode)['mirror'] ?? null;
    }
}
