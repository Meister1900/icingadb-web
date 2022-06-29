<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Model\Behavior;

use ipl\Orm\Contract\RewriteFilterBehavior;
use ipl\Orm\Contract\RewritePathBehavior;
use ipl\Stdlib\Filter;

class ReRoute implements RewriteFilterBehavior, RewritePathBehavior
{
    protected $routes;

    /**
     * Special tables which require an additional step to apply filters with service group name
     *
     * @var string[]
     */
    const SPECIAL_RELATIONS = ['downtime', 'comment', 'history', 'notification_history'];

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function rewriteCondition(Filter\Condition $condition, $relation = null)
    {
        $remainingPath = $condition->metaData()->get('columnName', '');
        if (strpos($remainingPath, '.') === false) {
            return;
        }

        if (($path = $this->rewritePath($remainingPath, $relation)) !== null) {
            $class = get_class($condition);
            $filter = new $class($relation . $path, $condition->getValue());
            if ($condition->metaData()->has('forceOptimization')) {
                $filter->metaData()->set(
                    'forceOptimization',
                    $condition->metaData()->get('forceOptimization')
                );
            }

            if (in_array(substr($relation, 0, -1), self::SPECIAL_RELATIONS) && $remainingPath === 'servicegroup.name') {
                $applyAll = Filter::all();
                $applyAll->add(Filter::like($relation . 'object_type', 'host'));
                $applyAll->add(Filter::like('host.' . $path, $condition->getValue()));

                $filter = Filter::any($filter, $applyAll);
            }

            return $filter;
        }
    }

    public function rewritePath(string $path, ?string $relation = null): ?string
    {
        $dot = strpos($path, '.');
        if ($dot !== false) {
            $routeName = substr($path, 0, $dot);
        } else {
            $routeName = $path;
        }

        if (isset($this->routes[$routeName])) {
            return $this->routes[$routeName] . ($dot !== false ? substr($path, $dot) : '');
        }

        return null;
    }
}
