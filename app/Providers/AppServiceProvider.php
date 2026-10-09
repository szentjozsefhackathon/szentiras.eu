<?php

namespace SzentirasHu\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Spatie\FlareClient\Api;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Service\Cdn\CacheTagCollector;
use SzentirasHu\Service\Cdn\CloudflareCacheService;
use SzentirasHu\Service\Editor\EditorService;
use Twig\Environment;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Environment $twig)
    {
        $twig->addExtension(new MarkdownExtension());
        $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
            public function load($class) {
                if (MarkdownRuntime::class === $class) {
                    return new MarkdownRuntime(new DefaultMarkdown());
                } else {
                    return null;
                }
            }
        });
        
        // Add isEditor function to Twig
        $twig->addFunction(new \Twig\TwigFunction('isEditor', function () {
            return app(EditorService::class)->currentIsEditor();
        }));
        
        // Add unreadMessageCount function to Twig
        $twig->addFunction(new \Twig\TwigFunction('unreadMessageCount', function () {
            $token = session('anonymous_token');
            if (!$token) {
                return 0;
            }
            
            $anonymousId = \SzentirasHu\Data\Entity\AnonymousId::where('token', $token)->first();
            if (!$anonymousId) {
                return 0;
            }
            
            return \SzentirasHu\Data\Entity\ContactMessage::where('receiver_anonymous_id', $anonymousId->id)
                ->where('is_read', false)
                ->count();
        }));

        // Define rate limiter for API keys
        RateLimiter::for('api_key', function ($request) {
            // Use the key already resolved by VerifyApiKey, so that keys passed as a
            // ?api_key= query parameter are throttled too. Re-reading the X-API-Key header
            // here would find nothing for those requests and grant them unlimited access.
            $apiKey = $request->attributes->get('apiKey');
            if (!$apiKey || $apiKey->isInternal()) {
                // No limit for internal keys or missing key (should not happen)
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }

            $rate = $apiKey->effectiveThrottleRate();
            if ($rate === null) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }

            return \Illuminate\Cache\RateLimiting\Limit::perMinute($rate)->by($apiKey->id);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(EditorService::class, function ($app) {
            return new EditorService();
        });
        
        $this->app->alias(EditorService::class, 'editor');

        // Register AI Prompt Service
        $this->app->singleton(AiPromptService::class, function ($app) {
            return new AiPromptService($app['config']);
        });
        
        $this->app->alias(AiPromptService::class, 'ai-prompt');

        $this->app->scoped(CacheTagCollector::class);

        $this->app->bind(CloudflareCacheService::class, function ($app) {
            return new CloudflareCacheService(
                $app['config']->get('services.cloudflare.zone_id'),
                $app['config']->get('services.cloudflare.api_token'),
                $app['config']->get('services.cloudflare.api_base_url'),
            );
        });

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

    }
}
