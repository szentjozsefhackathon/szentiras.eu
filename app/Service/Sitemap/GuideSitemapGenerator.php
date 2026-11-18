<?php

namespace SzentirasHu\Service\Sitemap;

use SzentirasHu\Models\FamousPassage;
use SzentirasHu\Models\Guide;

/**
 * The sitemap of the pages editors create and publish at any time: guides and famous passages.
 * Served dynamically, unlike the static sitemap of the Bible text, so new pages appear at once.
 */
class GuideSitemapGenerator
{
    public function generate(): string
    {
        $urls = '';

        foreach (Guide::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('slug') as $slug) {
            $url = route('guides.show', ['guide' => $slug]);
            $urls .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1)."</loc></url>\n";
        }

        $famousPassageSlugs = FamousPassage::query()->active()->pluck('slug');
        if ($famousPassageSlugs->isNotEmpty()) {
            $urls .= '  <url><loc>'.htmlspecialchars(route('famousPassages.index'), ENT_XML1)."</loc></url>\n";
        }
        foreach ($famousPassageSlugs as $slug) {
            $url = route('famousPassages.show', ['famousPassage' => $slug]);
            $urls .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1)."</loc></url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls
            .'</urlset>'."\n";
    }
}
