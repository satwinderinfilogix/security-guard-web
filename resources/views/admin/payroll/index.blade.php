@extends('layouts.app')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0 font-size-18">Payroll</h4>

                        <div class="page-title-right">
                            <a href="{{ route('payroll-export.guard', ['date' => request('date')]) }}" id="guardExportBtn"
                                class="btn btn-primary primary-btn btn-md me-1">
                                <i class="bx bx-download"></i> Guard Payroll Summary
                            </a>
                            <a href="javascript:void(0);" id="bulkDownloadBtn"
                                class="btn btn-primary primary-btn btn-md me-1"><i class="bx bx-download"></i> Bulk Download
                                PDFs</a>
                            <a href="{{ route('payroll-export.csv', ['date' => request('date')]) }}" id="exportBtn"
                                class="btn btn-primary primary-btn btn-md me-1"><i class="bx bx-download"></i> SO1
                                Report</a>
                            <a href="{{ url('download-payroll-sample') }}"
                                class="btn btn-primary primary-btn btn-md me-1"><i class="bx bx-download"></i> Payroll
                                Sample File</a>
                            <div class="d-inline-block ">
                                <form id="importForm" action="{{ route('import.payroll') }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <label for="fileInput" class="btn btn-primary primary-btn btn-md mb-0">
                                        <i class="bx bx-cloud-download"></i> Import Payroll
                                        <input type="file" id="fileInput" name="file" accept=".csv, .xlsx"
                                            style="display:none;">
                                    </label>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <form method="GET" id="attendance-form">
                        <div class="row">
                            <div class="col-md-3">
                                <input type="text" name="date" class="form-control" id="date"
                                    value="{{ \Carbon\Carbon::parse($previousFortnightStartDate)->format('Y-m-d') }} to {{ \Carbon\Carbon::parse($previousFortnightEndDate)->format('Y-m-d') }}"
                                    autocomplete="off">
                                {{-- <input type="text" id="date" name="date" class="form-control datePicker" value="{{ \Carbon\Carbon::parse($previousFortnightEndDate)->format('Y-m-d') }}" placeholder="Select Date Range" autocomplete="off"> --}}
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="searchBtn" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <x-error-message :message="$errors->first('message')" />
                    <x-success-message :message="session('success')" />
                    @if (session('downloadUrl'))
                        <script>
                            window.onload = function() {
                                window.location.href = "{{ session('downloadUrl') }}";
                            };
                        </script>
                    @endif
                    <div class="card">
                        <div class="card-body">
                            <table id="payroll-list" class="table table-bordered dt-responsive nowrap w-100">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Guard</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Normal Hours</th>
                                        <th>Overtime Hours</th>
                                        <th>Public Holiday Hours</th>
                                        @canany(['edit payroll', 'view payroll'])
                                            <th>Action</th>
                                        @endcanany
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <x-include-plugins :plugins="['datePicker', 'dataTable', 'import', 'dateRange']"></x-include-plugins>
    <script>
        $(document).ready(function() {
            flatpickr("#date", {
                mode: 'range',
                showMonths: 2,
            });

            function convertToHoursAndMinutes(fractionalHours) {
                var hours = Math.floor(fractionalHours);
                var minutes = Math.round((fractionalHours - hours) * 60); // Convert the fractional part to minutes
                return hours + ':' + minutes;
            }

            let actionColumn = [];
            @canany(['edit payroll', 'delete payroll'])
                actionColumn = [{
                    data: null,
                    render: function(data, type, row) {
                        var actions = '<div class="action-buttons">';

                        @can('edit payroll')
                            actions +=
                                `<a class="btn btn-primary waves-effect waves-light btn-sm edit" href="{{ url('admin/payrolls') }}/${row.id}/edit">`;
                            actions += '<i class="fas fa-pencil-alt"></i>';
                            actions += '</a>';
                        @endcan

                        @can('view payroll')
                            actions +=
                                `<a class="btn btn-danger waves-effect waves-light btn-sm edit" href="{{ url('admin/payrolls') }}/${row.id}">`;
                            actions += '<i class="fas fa-eye"></i>';
                            actions += '</a>';
                        @endcan
                        actions +=
                            `<button class="btn btn-primary btn-sm" onclick="downloadInvoicePdf('${row.id}')">`;
                        actions += '<i class="fas fa-file-pdf"></i>';
                        actions += '</button>';

                        actions += '</div>';
                        return actions;
                    }
                }];
            @endcanany

            window.downloadInvoicePdf = function(invoiceId) {
                window.location.href = "{{ route('payrolls.download-pdf', ':invoiceId') }}".replace(
                    ':invoiceId', invoiceId);
            };

            let payrollTable = $('#payroll-list').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('get-payroll-list') }}",
                    type: "POST",
                    data: function(d) {
                        d._token = "{{ csrf_token() }}";
                        d.date = $('#date').val();
                        return d;
                    },
                    dataSrc: function(json) {
                        return json.data || [];
                    }
                },
                columns: [{
                        data: null,
                        render: function(data, type, row, meta) {
                            return meta.row + 1 + meta.settings._iDisplayStart;
                        }
                    },
                    {
                        data: 'user.first_name'
                    },
                    {
                        data: 'start_date'
                    },
                    {
                        data: 'end_date'
                    },
                    {
                        data: 'normal_hours',
                        render: function(data) {
                            return convertToHoursAndMinutes(data); // Format normal_hours
                        }
                    },
                    {
                        data: 'overtime',
                        render: function(data) {
                            return convertToHoursAndMinutes(data); // Format overtime
                        }
                    },
                    {
                        data: 'public_holidays',
                        render: function(data) {
                            return convertToHoursAndMinutes(data); // Format public_holidays
                        }
                    },
                    ...actionColumn
                ],
                paging: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [
                    [0, 'asc']
                ]
            });

            $('#date').on('change', function() {
                var selectedDate = $('#date').val();
                var exportUrl = "{{ route('payroll-export.csv', ['date' => '__date__']) }}";
                exportUrl = exportUrl.replace('__date__', selectedDate);
                $('#exportBtn').attr('href', exportUrl);

                var guardExportUrl = "{{ route('payroll-export.guard', ['date' => '__date__']) }}".replace(
                    '__date__', selectedDate);
                $('#guardExportBtn').attr('href', guardExportUrl);
            });

            $('#searchBtn').on('click', function() {
                payrollTable.ajax.reload();
            });
            /*  $('#date').on('change', function() {
                 payrollTable.ajax.reload();
             }); */

            $('#bulkDownloadBtn').on('click', function() {
                var selectedDate = $('#date').val();
                var bulkDownloadUrl = "{{ route('payrolls.bulk-download-pdf', ['date' => '__date__']) }}";
                bulkDownloadUrl = bulkDownloadUrl.replace('__date__', selectedDate);
                window.location.href = bulkDownloadUrl;
            });
        });
    </script>
@endsection
