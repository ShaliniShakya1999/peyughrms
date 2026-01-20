<!DOCTYPE html>
<html>

<head>
    <title>Attendance Report {{ $year }}</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
    </style>
</head>

<body>
    <h2>Attendance Report - {{ $year }}</h2>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Date</th>
                <th>Status</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Shift</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendanceRecords as $record)
            <tr>
                <td>{{ optional($record->employee)->name }}</td>
                <td>{{ $record->date }}</td>
                <td>{{ ucfirst($record->status) }}</td>
                <td>{{ $record->clock_in ?? '-' }}</td>
                <td>{{ $record->clock_out ?? '-' }}</td>
                <td>{{ optional($record->shift)->name }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;">No Attendance Data Found</td>
            </tr>
            @endforelse
        </tbody>


    </table>
</body>

</html>