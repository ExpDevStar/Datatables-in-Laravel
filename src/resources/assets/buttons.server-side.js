(function ($, DataTable) {
    "use strict";

    var _buildUrl = function(dt, action) {
        var url = dt.ajax.url() || '';
        var params = dt.ajax.params();
        params.action = action;

        return url + '?' + $.param(params);
    };

    DataTable.ext.buttons.excel = {
        className: 'buttons-excel',

        text: function (dt) {
            return dt.i18n('buttons.excel', 'Excel');
        },

        action: function (e, dt, button, config) {
            var url = _buildUrl(dt, 'excel');
            window.location = url;
        }
    };

    DataTable.ext.buttons.csv = {
        className: 'buttons-csv',

        text: function (dt) {
            return dt.i18n('buttons.csv', 'CSV');
        },

        action: function (e, dt, button, config) {
            var url = _buildUrl(dt, 'csv');
            window.location = url;
        }
    };

    DataTable.ext.buttons.pdf = {
        className: 'buttons-pdf',

        text: function (dt) {
            return dt.i18n('buttons.pdf', 'PDF');
        },

        action: function (e, dt, button, config) {
            var url = _buildUrl(dt, 'pdf');
            window.location = url;
        }
    };

    DataTable.ext.buttons.print = {
        className: 'buttons-print',

        text: function (dt) {
            return dt.i18n('buttons.print', 'Print');
        },

        action: function (e, dt, button, config) {
            var url = _buildUrl(dt, 'print');
            window.location = url;
        }
    };

    DataTable.ext.buttons.refresh = {
        className: 'buttons-refresh',

        text: function (dt) {
            return dt.i18n('buttons.refresh', 'Refresh');
        },

        action: function (e, dt, button, config) {
            dt.search('').draw(false);
        }
    };

    DataTable.ext.buttons.reload = {
        className: 'buttons-reload',

        text: function (dt) {
            return dt.i18n('buttons.reload', 'Reload');
        },

        action: function (e, dt, button, config) {
            dt.ajax.reload();
        }
    };

    DataTable.ext.buttons.create = {
        className: 'buttons-create',

        text: function (dt) {
            return dt.i18n('buttons.create', 'Create');
        },

        action: function (e, dt, button, config) {
            window.location = window.location.href.replace(/\/+$/, "") + '/create';
        }
    };


})(jQuery, jQuery.fn.dataTable);
