# Demo Credentials

> **Demo only — not for production.** Every account below uses the password
> `password`, hashed with bcrypt and seeded by `php artisan db:seed`
> (see `api/database/seeders/UserSeeder.php`). These are intentionally weak
> and predictable so the system can be demoed quickly. Never reuse this
> password set, and never seed this data into a production database.

## Accounts

| Role | Username | Email | Password | Department |
|------|----------|-------|----------|------------|
| Admin | `admin` | `admin@jdcollege.edu.in` | `password` | — |
| Teacher | `teacher.cse` | `cse.teacher@jdcollege.edu.in` | `password` | CSE |
| Teacher | `teacher.it` | `it.teacher@jdcollege.edu.in` | `password` | IT |
| Teacher | `teacher.entc` | `entc.teacher@jdcollege.edu.in` | `password` | ENTC |
| Teacher | `teacher.mech` | `mech.teacher@jdcollege.edu.in` | `password` | MECH |
| Teacher | `teacher.civil` | `civil.teacher@jdcollege.edu.in` | `password` | CIVIL |
| HOD | `hod.cse` | `cse.hod@jdcollege.edu.in` | `password` | CSE |
| HOD | `hod.it` | `it.hod@jdcollege.edu.in` | `password` | IT |
| Principal | `principal` | `principal@jdcollege.edu.in` | `password` | — |

## Seed Data Summary

Running `docker-compose exec api php artisan db:seed` populates:

- 1 admin, 5 department teachers, 2 HODs (CSE, IT), 1 principal (9 users total)
- 5 departments: CSE, IT, ENTC, MECH, CIVIL
- 40 subjects (2 per department per semester, semesters 3-6)
- 200 students (40 per department, 10 per semester per department)
- ~6 months of weekday attendance per student across their 2 enrolled subjects,
  with randomized per-student attendance rates (55-97%) so a realistic subset
  of students fall below the 75% detention threshold

## Login Example

```bash
curl -s -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@jdcollege.edu.in","password":"password"}'
```
