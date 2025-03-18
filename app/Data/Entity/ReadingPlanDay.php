<?php

namespace SzentirasHu\Data\Entity;
use Eloquent;

/**
 * A day of a reading plan.
 *
 * @author Gabor Hosszu
 * @property int $id
 * @property int $plan_id
 * @property int $day_number
 * @property string $description
 * @property string $verses
 * @property-read \SzentirasHu\Data\Entity\ReadingPlan|null $plan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay whereDayNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReadingPlanDay whereVerses($value)
 * @mixin Eloquent
 */
class ReadingPlanDay extends Eloquent {

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['plan_id', 'day_number', 'description', 'verses'];

    public function plan() {
        return $this->belongsTo('SzentirasHu\\Data\\Entity\\ReadingPlan', 'plan_id');
    }

}
