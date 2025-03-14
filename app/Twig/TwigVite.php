<?php
namespace SzentirasHu\Twig;

use Illuminate\Foundation\Vite as IlluminateVite;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class TwigVite extends AbstractExtension
{
    /**
     * @var string|object
     */
    protected $callback = 'Illuminate\Foundation\Vite';

    /**
     * Return the string object callback.
     *
     * @return string|object
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * Set a new string callback.
     *
     * @param string|object $callback
     *
     * @return void
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'TwigBridge_Extension_Laravel_Vite';
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction(
                'vite',
                function (...$arguments) {
                    $html = app(IlluminateVite::class)(...$arguments);
                    return $html->toHtml();
                }
            ),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getFilters()
    {
        return [];
    }
}
