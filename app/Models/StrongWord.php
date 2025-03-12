<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;

class StrongWord extends Model
{
    
    public function greekVerses() {
        return $this->belongsToMany(GreekVerse::class);
    }

}
