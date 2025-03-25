<?php

namespace SzentirasHu\Http\Controllers\Home;

use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Data\Entity\Article;
use SzentirasHu\Data\Repository\TranslationRepository;

/**
 *
 * Controller for the home page.
 * Note that many parts on the home view are coming from view composers.
 */
class InfoController extends Controller
{

    public function getIndex()
    {
        return \View::make("info.info", [
            'news' => Article::where('frontpage', true)->orderBy('publish_date', 'desc')->get(),
        ]);
    }

    public function mission()
    {
        return view("info.mission");
    }


}