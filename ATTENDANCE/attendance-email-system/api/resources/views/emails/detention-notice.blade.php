<x-mail::message>
# Detention Notice

Dear Parent / Guardian of **{{ $student->student_name }}**,

We regret to inform you that your ward has been placed on the **detention list** for **{{ $month }}** due to low attendance.

**Details:**

- **Student:** {{ $student->student_name }}
- **Roll Number:** {{ $student->roll_number }}
- **Department:** {{ $student->department }}
- **Semester:** {{ $student->semester }}
- **Month:** {{ $month }}
- **Attendance Percentage:** {{ $detentionData['attendance_percentage'] ?? 'N/A' }}%
- **Required Minimum:** {{ $detentionData['required_percentage'] ?? 75 }}%

A student who remains on the detention list may be **barred from appearing in examinations**. Please ensure your ward improves attendance immediately.

For an appeal or further information, please contact the HOD of the {{ $student->department }} department.

Thanks,<br>
**JD College Attendance System**
</x-mail::message>
