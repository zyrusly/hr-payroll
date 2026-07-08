<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Schedule Calendar') }}</title>
    <style>
        body {
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            margin: 18px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 12px;
        }

        table.calendar {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .calendar th,
        .calendar td {
            border: 1px solid #d1d5db;
            vertical-align: top;
            width: 14.285%;
        }

        .calendar th {
            background: #f3f4f6;
            font-size: 10px;
            padding: 6px;
            text-align: center;
        }

        .calendar td {
            height: 95px;
            padding: 5px;
        }

        .muted {
            background: #f9fafb;
            color: #9ca3af;
        }

        .date {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .entry {
            border-left: 3px solid #2f80ed;
            border-radius: 4px;
            font-size: 8px;
            line-height: 1.3;
            margin-bottom: 4px;
            padding: 4px;
        }

        .entry strong {
            display: block;
            font-size: 8px;
        }

        .empty {
            color: #9ca3af;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <h1>{{ __('Schedule Calendar') }}</h1>
    <div class="subtitle">
        {{ $calendar['month']->format('F Y') }}
        @if($filters['q'] || $filters['branch_id'] || $filters['employee_id'] || $filters['week_number'] || $filters['year'])
            | {{ __('Filtered export') }}
        @endif
    </div>

    <table class="calendar">
        <thead>
            <tr>
                @foreach([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')] as $weekday)
                    <th>{{ $weekday }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach(array_chunk($calendar['days'], 7) as $week)
                <tr>
                    @foreach($week as $day)
                        <td class="{{ $day['is_current_month'] ? '' : 'muted' }}">
                            <div class="date">{{ $day['date']->format('M d') }}</div>

                            @forelse($day['entries'] as $entry)
                                <div
                                    class="entry"
                                    style="border-left-color: {{ $entry['branch_color'] }}; background: {{ $entry['branch_background'] }}; color: {{ $entry['branch_text_color'] }};"
                                >
                                    <strong>{{ $entry['time'] }}</strong>
                                    <div>{{ $entry['employee_name'] }} @if($entry['employee_code'])({{ $entry['employee_code'] }})@endif</div>
                                    <div>{{ $entry['branch_name'] }} @if($entry['branch_code'])({{ $entry['branch_code'] }})@endif</div>
                                </div>
                            @empty
                                <div class="empty">{{ __('No schedules') }}</div>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
