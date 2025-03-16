<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;

/**
 * Reading plan.
 *
 * @author Gabor Hosszu
 * @property int $id
 * @property string $name
 * @property string $description
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \SzentirasHu\Data\Entity\ReadingPlanDay> $days
 * @property-read int|null $days_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlan whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlan whereName($value)
 * @mixin Eloquent
 */
class ReadingPlan extends Eloquent {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'description'];

    public function days() {
        return $this->hasMany('SzentirasHu\\Data\\Entity\\ReadingPlanDay', 'plan_id');
    }

}
