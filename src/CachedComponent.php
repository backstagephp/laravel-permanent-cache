<?php

namespace Backstage\PermanentCache\Laravel;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * @method mixed get(array $parameters = [], bool $update = false)
 */
abstract class CachedComponent extends Component
{
    use CachesValue;

    /** {@inheritdoc} */
    public function resolveView()
    {
        if (
            $this->isUpdating
        ) {
            return parent::resolveView();
        }

        $parameters = $this->getParameters();

        return new HtmlString((string) $this->get(parameters: $parameters, update: ! $this->isCached($parameters)));
    }
}
