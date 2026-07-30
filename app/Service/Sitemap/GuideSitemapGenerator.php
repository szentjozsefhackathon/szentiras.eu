<?php

namespace SzentirasHu\Service\Sitemap;

use SzentirasHu\Models\Guide;

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

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls
            .'</urlset>'."\n";
    }
}
