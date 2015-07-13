<?php

namespace yajra\Datatables;

use DateTime;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class Helper
{

    /**
     * Places item of extra columns into results by care of their order.
     *
     * @param  $item
     * @param  $array
     * @return array
     */
    public static function includeInArray($item, $array)
    {
        if ($item['order'] === false) {
            return array_merge($array, [$item['name'] => $item['content']]);
        } else {
            $count = 0;
            $last  = $array;
            $first = [];
            foreach ($array as $key => $value) {
                if ($count == $item['order']) {
                    return array_merge($first, [$item['name'] => $item['content']], $last);
                }

                unset($last[$key]);
                $first[$key] = $value;

                $count++;
            }
        }
    }

    /**
     * Determines if content is callable or blade string, processes and returns.
     *
     * @param string|callable $content Pre-processed content
     * @param array $data data to use with blade template
     * @param mixed $param parameter to call with callable
     * @return string Processed content
     */
    public static function compileContent($content, array $data, $param)
    {
        if (is_string($content)) {
            return static::compileBlade($content, static::getMixedValue($data, $param));
        } elseif (is_callable($content)) {
            return $content($param);
        }

        return $content;
    }

    /**
     * Parses and compiles strings by using Blade Template System.
     *
     * @param string $str
     * @param array $data
     * @return string
     */
    public static function compileBlade($str, $data = [])
    {
        $empty_filesystem_instance = new Filesystem();
        $blade                     = new BladeCompiler($empty_filesystem_instance, 'datatables');
        $parsed_string             = $blade->compileString($str);

        ob_start() && extract($data, EXTR_SKIP);
        eval('?>' . $parsed_string);
        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * @param  array $data
     * @param  mixed $param
     * @return array
     */
    public static function getMixedValue(array $data, $param)
    {
        $param = self::castToArray($param);

        foreach ($data as $key => $value) {
            if (isset($param[$key])) {
                $data[$key] = $param[$key];
            }
        }

        return $data;
    }

    /**
     * @param $param
     * @return array
     */
    public static function castToArray($param)
    {
        if ($param instanceof \stdClass) {
            $param = (array) $param;

            return $param;
        }

        return $param;
    }

    /**
     * Get equivalent or method of query builder.
     *
     * @param string $method
     * @return string
     */
    public static function getOrMethod($method)
    {
        if ( ! Str::contains(Str::lower($method), 'or')) {
            return 'or' . ucfirst($method);
        }

        return $method;
    }

    /**
     * Wrap value depending on database type.
     *
     * @param string $database
     * @param string $value
     * @return string
     */
    public static function wrapDatabaseValue($database, $value)
    {
        $parts  = explode('.', $value);
        $column = '';
        foreach ($parts as $key) {
            $column = static::wrapDatabaseColumn($database, $key, $column);
        }

        return substr($column, 0, strlen($column) - 1);
    }

    /**
     * Database column wrapper
     *
     * @param string $database
     * @param string $key
     * @param string $column
     * @return string
     */
    public static function wrapDatabaseColumn($database, $key, $column)
    {
        switch ($database) {
            case 'mysql':
                $column .= '`' . str_replace('`', '``', $key) . '`' . '.';
                break;

            case 'sqlsrv':
                $column .= '[' . str_replace(']', ']]', $key) . ']' . '.';
                break;

            case 'pgsql':
            case 'sqlite':
                $column .= '"' . str_replace('"', '""', $key) . '"' . '.';
                break;

            default:
                $column .= $key . '.';
        }

        return $column;
    }

    /**
     * Converts array object values to associative array.
     *
     * @param mixed $row
     * @return array
     */
    public static function convertToArray($row)
    {
        $data = $row instanceof Arrayable ? $row->toArray() : (array) $row;
        foreach (array_keys($data) as $key) {
            if (is_object($data[$key]) || is_array($data[$key])) {
                $data[$key] = self::convertToArray($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    public static function transform(array $data)
    {
        return array_map(function ($row) {
            return self::transformRow($row);
        }, $data);
    }

    /**
     * @param $row
     * @return mixed
     */
    protected static function transformRow($row)
    {
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            } else {
                if (is_string($value)) {
                    $row[$key] = (string) $value;
                } else {
                    $row[$key] = $value;
                }
            }
        }

        return $row;
    }
}
