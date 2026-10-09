<?php

namespace SzentirasHu\Data\Enum;

/**
 * Lifecycle of a manual verse text fix.
 * - Active: the fix is applied to the stored verse text.
 * - Conflict: after an import, neither the original nor the fixed text was found, the editor has to review it.
 * - ResolvedUpstream: after an import, the source already contains the fixed text, so the fix is no longer needed.
 * - Disabled: the editor withdrew the fix, the original text is restored.
 */
enum VerseFixStatus: string
{
    case Active = 'active';
    case Conflict = 'conflict';
    case ResolvedUpstream = 'resolved_upstream';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktív',
            self::Conflict => 'Ütközés',
            self::ResolvedUpstream => 'Forrásban javítva',
            self::Disabled => 'Kikapcsolva',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-success',
            self::Conflict => 'bg-danger',
            self::ResolvedUpstream => 'bg-info',
            self::Disabled => 'bg-secondary',
        };
    }
}
