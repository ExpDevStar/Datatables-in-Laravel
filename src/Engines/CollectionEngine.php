<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class CollectionEngine.
 *
 * @package Yajra\Datatables\Engines
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class CollectionEngine extends BaseEngine
{
    /**
     * Collection object
     *
     * @var \Illuminate\Support\Collection
     */
    public $collection;

    /**
     * Collection object
     *
     * @var \Illuminate\Support\Collection
     */
    public $original;

    /**
     * CollectionEngine constructor.
     *
     * @param \Illuminate\Support\Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->request    = resolve('datatables.request');
        $this->config     = resolve('datatables.config');
        $this->collection = $collection;
        $this->original   = $collection;
        $this->columns    = array_keys($this->serialize($collection->first()));
    }

    /**
     * Serialize collection
     *
     * @param  mixed $collection
     * @return mixed|null
     */
    protected function serialize($collection)
    {
        return $collection instanceof Arrayable ? $collection->toArray() : (array) $collection;
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    public function showDebugger(array $output)
    {
        $output["input"] = $this->request->all();

        return $output;
    }

    /**
     * Count results.
     *
     * @return integer
     */
    public function count()
    {
        return $this->collection->count() > $this->totalRecords ? $this->totalRecords : $this->collection->count();
    }

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $this->isFilterApplied = true;

                $regex   = $this->request->isRegex($i);
                $column  = $this->getColumnName($i);
                $keyword = $this->request->columnKeyword($i);

                $this->collection = $this->collection->filter(
                    function ($row) use ($column, $keyword, $regex) {
                        $data = $this->serialize($row);

                        $value = Arr::get($data, $column);

                        if ($this->config->isCaseInsensitive()) {
                            if ($regex) {
                                return preg_match('/' . $keyword . '/i', $value) == 1;
                            } else {
                                return strpos(Str::lower($value), Str::lower($keyword)) !== false;
                            }
                        } else {
                            if ($regex) {
                                return preg_match('/' . $keyword . '/', $value) == 1;
                            } else {
                                return strpos($value, $keyword) !== false;
                            }
                        }
                    }
                );
            }
        }
    }

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging()
    {
        $this->collection = $this->collection->slice(
            $this->request->input('start'),
            (int) $this->request->input('length') > 0 ? $this->request->input('length') : 10
        );
    }

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = true)
    {
        try {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $results   = $this->results();
                $processed = $this->processResults($results, $mDataSupport);
                $output    = $this->transform($results, $processed);

                $this->collection = collect($output);
                $this->ordering();
                $this->filterRecords();
                $this->paginate();
            }

            return $this->render($this->collection->values()->all());
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Count total items.
     *
     * @return integer
     */
    public function totalCount()
    {
        return $this->totalRecords ? $this->totalRecords : $this->collection->count();
    }

    /**
     * Get results.
     *
     * @return mixed
     */
    public function results()
    {
        return $this->collection->all();
    }

    /**
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        $columns = $this->request->columns();
        $keyword = $this->config->isCaseInsensitive() ? Str::lower($keyword) : $keyword;

        $this->collection = $this->collection->filter(function ($row) use ($columns, $keyword) {
            $this->isFilterApplied = true;

            $data = $this->serialize($row);
            foreach ($this->request->searchableColumnIndex() as $index) {
                $column = $this->getColumnName($index);
                $value  = Arr::get($data, $column);
                if (!$value || is_array($value)) {
                    continue;
                }

                $value = $this->config->isCaseInsensitive() ? Str::lower($value) : $value;
                if (Str::contains($value, $keyword)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Perform default query orderBy clause.
     */
    protected function defaultOrdering()
    {
        $criteria = $this->request->orderableColumns();
        if (! empty($criteria)) {
            $sorter = function ($a, $b) use ($criteria) {
                foreach ($criteria as $orderable) {
                    $column = $this->getColumnName($orderable['column']);
                    $direction = $orderable['direction'];
                    if ($direction === 'desc') {
                        $first = $b;
                        $second = $a;
                    } else {
                        $first = $a;
                        $second = $b;
                    }
                    if ($this->config->isCaseInsensitive()) {
                        $cmp = strnatcasecmp($first[$column], $second[$column]);
                    } else {
                        $cmp = strnatcmp($first[$column], $second[$column]);
                    }
                    if ($cmp != 0) {
                        return $cmp;
                    }
                }
                // all elements were equal
                return 0;
            };
            $this->collection = $this->collection->sort($sorter);
        }
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return $this
     */
    protected function resolveCallbackParameter()
    {
        return $this;
    }
}
