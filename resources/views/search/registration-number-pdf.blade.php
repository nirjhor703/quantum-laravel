<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Participants by Registration Number</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #151b27; font-size: 10px; }
        h2 { margin: 0 0 4px; font-size: 18px; }
        p { margin: 0 0 14px; color: #5d6675; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccd3dd; padding: 6px; vertical-align: middle; }
        th { background: #dfe7f2; text-align: center; font-weight: bold; }
        .center { text-align: center; }
        img { width: 54px; height: 62px; object-fit: cover; }
    </style>
</head>
<body>
    <h2>Search Results</h2>
    <p>{{ $participants->count() }} participants found</p>
    <table>
        <thead>
            <tr>
                <th>Sl</th><th>Img</th><th>Age</th><th>Occupation</th><th>Status</th>
                <th>Name</th><th>Branch</th><th>Reg No.</th><th>Mobile</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($participants as $index => $participant)
                @php
                    $imagePath = $participant['image'] ? public_path('storage/'.$participant['image']) : null;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center">
                        <strong>{{ $participant['sl'] }}</strong><br>
                        @if ($imagePath && file_exists($imagePath))
                            <img src="{{ $imagePath }}" alt="">
                        @else
                            —
                        @endif
                    </td>
                    <td class="center">{{ $participant['age'] ?: '—' }}</td>
                    <td>{{ $participant['occupation'] ?: '—' }}</td>
                    <td>{{ $participant['status'] ?: '—' }}</td>
                    <td>{{ $participant['name'] }}</td>
                    <td>{{ $participant['branch'] ?: '—' }}</td>
                    <td>{{ $participant['reg_no'] }}</td>
                    <td>{{ $participant['mobile'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
