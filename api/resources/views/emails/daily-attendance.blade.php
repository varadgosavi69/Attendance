<x-mail::message>
# Daily Attendance Report

Dear Parent / Guardian of **{{ $student->student_name }}**,

Here is today's attendance summary for **{{ $date }}**:

<x-mail::table>
| Subject | Status | Marked At |
|---------|--------|-----------|
@foreach ($attendanceData as $record)
| {{ $record['subject'] }} | {{ ucfirst($record['status']) }} | {{ $record['marked_at'] }} |
@endforeach
</x-mail::table>

Please ensure your ward maintains regular attendance to avoid academic setbacks.

For queries, contact the college office during working hours.

Thanks,<br>
**JD College Attendance System**
</x-mail::message>
