<?php

namespace SzentirasHu\Data\Repository;


use SzentirasHu\Data\Entity\ReadingPlan;
use SzentirasHu\Data\Entity\ReadingPlanDay;

interface ReadingPlanRepository {
    /**
     * List all the reading plans
     *
     * @return ReadingPlan[]
     */
    public function getAll();

    /**
     * Get a reading plan by id
     *
     * @param int $id
     * @return ReadingPlan
     */
    public function getReadingPlanByPlanId($id) : ?ReadingPlan;
}
