<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;

class GreekVerse extends Model
{
    public function strongWords() {
        return $this->belongsToMany(StrongWord::class)->withPivot('strong_word_instances');
    }
}
