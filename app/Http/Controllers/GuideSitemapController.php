<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Http\Response;
use SzentirasHu\Service\Sitemap\GuideSitemapGenerator;

class GuideSitemapController extends Controller
{
    public function __construct(
        protected GuideSitemapGenerator $guideSitemapGenerator
    ) {}

    public function __invoke(): Response
    {
        return response(
            $this->guideSitemapGenerator->generate(),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
