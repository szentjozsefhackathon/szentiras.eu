<?php

namespace SzentirasHu\Data\Entity;

enum EmbeddedExcerptScope: string
{
    case Verse = 'verse';
    case Chapter = 'chapter';
    case Range = 'range';
}
