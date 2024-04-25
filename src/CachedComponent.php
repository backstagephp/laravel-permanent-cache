<?php

namespace Vormkracht10\PermanentCache;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

abstract class CachedComponent extends Component
{
    use CachesValue;

    /** {@inheritdoc} */
    public function resolveView()
    {
        if (
            $this->isUpdating ||
            $this->shouldBeUpdating()
        ) {
            return parent::resolveView();
        }

        if (null !== $cachedValue = $this->get($this->getParameters())) {
            return new HtmlString((string) $cachedValue);
        }
    }
}
