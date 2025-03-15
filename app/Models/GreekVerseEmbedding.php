<?php

namespace SzentirasHu\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

class GreekVerseEmbedding extends Model
{
    public $timestamps = false;

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
