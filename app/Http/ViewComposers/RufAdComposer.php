<?php
namespace SzentirasHu\Http\ViewComposers;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;

class RufAdComposer
{
    public function compose($view)
    {
        $path = Request::path();
        // Show ad if path is exactly 'RUF' or starts with 'RUF/'
        $showRufAd = ($path === 'RUF' || strpos($path, 'RUF/') === 0);
        $view->with('showRufAd', $showRufAd);

        // Download and cache (in minutes) RSS feed (cache only parsed array, not SimpleXMLElement)
        $rufFeedItems = Cache::remember('ruf_rss_feed', 600 , function () {
            if (config('app.debug')) {
                \Log::debug('RUFAdComposer: Cache miss, fetching RSS feed.');
            }
            try {
                $rss = @file_get_contents('https://bibliatarsulat.hu/feed/');
                if (config('app.debug')) {
                    \Log::debug('RUFAdComposer: file_get_contents result', ['success' => $rss !== false]);
                }
                if ($rss === false) return [];
                $xml = @simplexml_load_string($rss);
                if (config('app.debug')) {
                    \Log::debug('RUFAdComposer: simplexml_load_string result', ['is_object' => is_object($xml), 'class' => is_object($xml) ? get_class($xml) : null]);
                }
                if ($xml === false) return [];
                $items = [];
                $i = 0;
                if (isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $item) {
                        if ($i++ >= 5) break;
                        $date = strtotime((string)$item->pubDate);
                        $items[] = [
                            'title' => (string)$item->title,
                            'url' => (string)$item->link,
                            'date' => date('Y-m-d H:i:s', $date),
                            'date_diff_hu' => self::diffForHumansHu($date)
                        ];
                        if (config('app.debug')) {
                            \Log::debug('RUFAdComposer: feed item', [
                                'title' => (string)$item->title,
                                'pubDate' => (string)$item->pubDate,
                                'timestamp' => $date,
                                'date_diff_hu' => self::diffForHumansHu($date)
                            ]);
                        }
                    }
                }
                return $items;
            } catch (\Exception $e) {
                if (config('app.debug')) {
                    \Log::error('RUFAdComposer: Exception while fetching RSS feed', ['exception' => $e]);
                }
                return [];
            }
        });
        if (config('app.debug')) {
            \Log::debug('RUFAdComposer: Cache used: ' . (Cache::has('ruf_rss_feed') ? 'yes' : 'no'));
        }
        $view->with('rufFeedItems', $rufFeedItems);
    }

    // Magyar "diff for humans" helper
    private static function diffForHumansHu($timestamp)
    {
        $now = time();
        $diff = $now - $timestamp;
        if ($diff < 60) {
            return 'néhány másodperce';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' perce';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' órája';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' napja';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' hónapja';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' éve';
        }
    }
}
