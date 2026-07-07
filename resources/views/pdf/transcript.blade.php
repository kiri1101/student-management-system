<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #111; font-size: 11px; }
        .brand { color: #047857; font-size: 20px; font-weight: bold; }
        .institution { font-size: 12px; color: #555; margin-bottom: 2px; }
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; margin: 12px 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        table.identity { width: 100%; margin: 10px 0 6px; }
        table.identity td { padding: 2px 4px; font-size: 11px; }
        table.identity .label { color: #555; width: 18%; }
        .sem-heading { font-weight: bold; font-size: 11px; margin-top: 14px; color: #065f46; }
        table.results { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.results th { background: #ecfdf5; color: #065f46; text-align: left; padding: 4px 6px; border-bottom: 1px solid #a7f3d0; font-size: 10px; }
        table.results td { padding: 4px 6px; border-bottom: 1px solid #eee; font-size: 10px; }
        .sem-summary { text-align: right; font-size: 10px; color: #333; padding: 4px 6px; font-weight: bold; }
        .cumulative { margin-top: 16px; padding: 8px; background: #f0fdf4; border: 1px solid #a7f3d0; font-weight: bold; font-size: 11px; }
        .verify { margin-top: 18px; font-size: 9px; color: #555; }
        .verify .qr { float: right; width: 120px; }
    </style>
</head>
<body>
    <div>
        <span class="brand">SchuLyf</span>
        <div class="institution">SchuLyf University · Yaoundé, Cameroon</div>
    </div>
    <div class="doc-title">Official Academic Transcript</div>

    <table class="identity">
        <tr>
            <td class="label">Student</td><td>{{ $snapshot['student']['name'] ?? '—' }}</td>
            <td class="label">Transcript №</td><td>{{ $transcript->transcript_number }}</td>
        </tr>
        <tr>
            <td class="label">Matricule</td><td>{{ $snapshot['student']['matricule'] ?? '—' }}</td>
            <td class="label">Issued</td><td>{{ $transcript->issued_at?->toFormattedDateString() }}</td>
        </tr>
        <tr>
            <td class="label">Programme</td><td>{{ $snapshot['student']['programme'] ?? '—' }}</td>
            <td class="label">Current level</td><td>{{ $snapshot['student']['level'] ?? '—' }}</td>
        </tr>
    </table>

    @foreach ($snapshot['semesters'] as $semester)
        <div class="sem-heading">{{ $semester['academic_year'] }} · Semester {{ $semester['semester'] }}</div>
        <table class="results">
            <thead>
                <tr>
                    <th>Code</th><th>Course title</th><th>Credits</th><th>Score</th><th>Grade</th><th>Points</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($semester['courses'] as $course)
                    <tr>
                        <td>{{ $course['code'] }}</td>
                        <td>{{ $course['title'] }}</td>
                        <td>{{ $course['credits'] }}</td>
                        <td>{{ $course['score'] }}%</td>
                        <td>{{ $course['grade'] }}</td>
                        <td>{{ number_format($course['points'], 1) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="6" class="sem-summary">
                        Semester GPA {{ number_format($semester['gpa'], 2) }} ·
                        Credits {{ $semester['credits_earned'] }}/{{ $semester['credits_attempted'] }}
                    </td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="cumulative">
        Cumulative: CGPA {{ number_format($snapshot['cumulative']['cgpa'], 2) }} ·
        Credits earned {{ $snapshot['cumulative']['credits_earned'] }} /
        attempted {{ $snapshot['cumulative']['credits_attempted'] }} ·
        {{ $snapshot['cumulative']['total_courses'] }} courses
    </div>

    <div class="verify">
        <div class="qr">{!! $qrSvg !!}</div>
        <p><strong>Verify authenticity</strong> at:<br>{{ $verifyUrl }}</p>
        <p>This is an official SchuLyf transcript. Scan the QR or visit the URL above to confirm it has not been altered.</p>
    </div>
</body>
</html>
