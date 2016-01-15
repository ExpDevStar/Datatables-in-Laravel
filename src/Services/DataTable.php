<?php

namespace Yajra\Datatables\Services;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;
use Maatwebsite\Excel\Writers\LaravelExcelWriter;
use Yajra\Datatables\Contracts\DataTableButtonsContract;
use Yajra\Datatables\Contracts\DataTableContract;
use Yajra\Datatables\Contracts\DataTableScopeContract;
use Yajra\Datatables\Datatables;

abstract class DataTable implements DataTableContract, DataTableButtonsContract
{
    /**
     * @var \Yajra\Datatables\Datatables
     */
    protected $datatables;

    /**
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $viewFactory;

    /**
     * Datatables print preview view.
     *
     * @var string
     */
    protected $printPreview;

    /**
     * List of columns to be exported.
     *
     * @var string|array
     */
    protected $exportColumns = '*';

    /**
     * List of columns to be printed.
     *
     * @var string|array
     */
    protected $printColumns = '*';

    /**
     * Query scopes.
     *
     * @var \Yajra\Datatables\Contracts\DataTableScopeContract[]
     */
    protected $scopes = [];

    /**
     * @param \Yajra\Datatables\Datatables $datatables
     * @param \Illuminate\Contracts\View\Factory $viewFactory
     */
    public function __construct(Datatables $datatables, Factory $viewFactory)
    {
        $this->datatables  = $datatables;
        $this->viewFactory = $viewFactory;
    }

    /**
     * Render view.
     *
     * @param $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function render($view, $data = [], $mergeData = [])
    {
        if ($this->request()->ajax() && $this->request()->wantsJson()) {
            return $this->ajax();
        }

        switch ($this->datatables->getRequest()->get('action')) {
            case 'excel':
                return $this->excel();

            case 'csv':
                return $this->csv();

            case 'pdf':
                return $this->pdf();

            case 'print':
                return $this->printPreview();

            default:
                return $this->viewFactory->make($view, $data, $mergeData)->with('dataTable', $this->html());
        }
    }

    /**
     * Get Datatables Request instance.
     *
     * @return \Yajra\Datatables\Request
     */
    public function request()
    {
        return $this->datatables->getRequest();
    }

    /**
     * Export results to Excel file.
     *
     * @return mixed
     */
    public function excel()
    {
        return $this->buildExcelFile()->download('xls');
    }

    /**
     * Build excel file and prepare for export.
     *
     * @return mixed
     */
    protected function buildExcelFile()
    {
        return app('excel')->create($this->filename(), function (LaravelExcelWriter $excel) {
            $excel->sheet('exported-data', function (LaravelExcelWorksheet $sheet) {
                $sheet->fromArray($this->getDataForExport());
            });
        });
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'export_' . time();
    }

    /**
     * Get mapped columns versus final decorated output.
     *
     * @return array
     */
    protected function getDataForExport()
    {
        $decoratedData = $this->getAjaxResponseData();

        return array_map(function ($row) {
            if ($columns = $this->exportColumns()) {
                return $this->buildExportColumn($row, $columns);
            }

            return $row;
        }, $decoratedData);
    }

    /**
     * Get decorated data as defined in datatables ajax response.
     *
     * @return mixed
     */
    protected function getAjaxResponseData()
    {
        $this->datatables->getRequest()->merge(['length' => -1]);

        $response = $this->ajax();
        $data     = $response->getData(true);

        return $data['data'];
    }

    /**
     * Get export columns definition.
     *
     * @return array|string
     */
    private function exportColumns()
    {
        return is_array($this->exportColumns) ? $this->exportColumns : $this->getColumnsFromBuilder();
    }

    /**
     * Get columns definition from html builder.
     *
     * @return array
     */
    protected function getColumnsFromBuilder()
    {
        return $this->html()->getColumns();
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\Datatables\Html\Builder
     */
    public function html()
    {
        return $this->builder();
    }

    /**
     * Get Datatables Html Builder instance.
     *
     * @return \Yajra\Datatables\Html\Builder
     */
    public function builder()
    {
        return $this->datatables->getHtmlBuilder();
    }

    /**
     * @param array $row
     * @param array|\Illuminate\Support\Collection $columns
     * @return array
     */
    protected function buildExportColumn(array $row, $columns)
    {
        return $this->buildColumn($row, $columns, 'exportable');
    }

    /**
     * Build printable and exportable column.
     *
     * @param array $row
     * @param array|\Illuminate\Support\Collection $columns
     * @param string $type
     * @return array
     */
    protected function buildColumn(array $row, $columns, $type)
    {
        if ($columns instanceof Collection) {
            return $this->buildColumnByCollection($row, $columns, $type);
        }

        return array_only($row, $columns);
    }

    /**
     * Build column from collection.
     *
     * @param array $row
     * @param \Illuminate\Support\Collection $columns
     * @param string $type
     * @return array
     */
    protected function buildColumnByCollection(array  $row, Collection $columns, $type)
    {
        $results = [];
        foreach ($columns->all() as $column) {
            if ($column[$type]) {
                $data = array_get($row, $column['data']);

                $results[$column['title']] = $type == 'exportable' ? strip_tags($data) : $data;
            }
        }

        return $results;
    }

    /**
     * Export results to CSV file.
     *
     * @return mixed
     */
    public function csv()
    {
        return $this->buildExcelFile()->download('csv');
    }

    /**
     * Export results to PDF file.
     *
     * @return mixed
     */
    public function pdf()
    {
        return $this->buildExcelFile()->download('pdf');
    }

    /**
     * Display printable view of datatables.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function printPreview()
    {
        $data = $this->getDataForPrint();
        $view = $this->printPreview ?: 'datatables::print';

        return $this->viewFactory->make($view, compact('data'));
    }

    /**
     * Get mapped columns versus final decorated output.
     *
     * @return array
     */
    protected function getDataForPrint()
    {
        $decoratedData = $this->getAjaxResponseData();

        return array_map(function ($row) {
            if ($columns = $this->printColumns()) {
                return $this->buildPrintColumn($row, $columns);
            }

            return $row;
        }, $decoratedData);
    }

    /**
     * Get printable columns.
     *
     * @return array|string
     */
    protected function printColumns()
    {
        return is_array($this->printColumns) ? $this->printColumns : $this->getColumnsFromBuilder();
    }

    /**
     * Build printable column.
     *
     * @param array $row
     * @param array|\Illuminate\Support\Collection $columns
     * @return array
     */
    protected function buildPrintColumn(array $row, $columns)
    {
        return $this->buildColumn($row, $columns, 'printable');
    }

    /**
     * Add basic array query scopes.
     *
     * @param \Yajra\Datatables\Contracts\DataTableScopeContract $scope
     * @return $this
     */
    public function addScope(DataTableScopeContract $scope)
    {
        $this->scopes[] = $scope;

        return $this;
    }

    /**
     * Apply query scopes.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    protected function applyScopes($query)
    {
        foreach ($this->scopes as $scope) {
            $scope->apply($query);
        }

        return $query;
    }

    /**
     * Get default builder parameters.
     *
     * @return array
     */
    protected function getBuilderParameters()
    {
        return [
            'order'   => [[0, 'desc']],
            'buttons' => [
                'create',
                [
                    'extend'  => 'collection',
                    'text'    => '<i class="fa fa-download"></i> Export',
                    'buttons' => [
                        'csv',
                        'excel',
                        'pdf',
                    ],
                ],
                'print',
                'reset',
                'reload',
            ],
        ];
    }
}
