"""MySQL read-replica connection (SQLAlchemy) + raw data loaders.

All training and feature-building reads go through here so they consistently
hit the *replica* (DB_READ_HOST/PORT) — keeping heavy analytical scans off the
primary, the same `read`/`write` separation Laravel already uses for the API.
"""
from __future__ import annotations

from datetime import date
from functools import lru_cache

import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine

from app.config import get_settings


@lru_cache(maxsize=1)
def get_engine() -> Engine:
    settings = get_settings()
    url = (
        f"mysql+pymysql://{settings.db_username}:{settings.db_password}"
        f"@{settings.db_read_host}:{settings.db_read_port}/{settings.db_database}"
        "?charset=utf8mb4"
    )
    return create_engine(url, pool_pre_ping=True, pool_recycle=1800)


def load_attendance_history(
    student_ids: list[int] | None = None,
    since: date | None = None,
) -> pd.DataFrame:
    """Raw attendance rows joined with the student's department/semester —
    the single source both feature engineering and label construction read from.

    Columns: student_id, subject_id, attendance_date, status, department, semester
    """
    engine = get_engine()

    query = """
        SELECT
            a.student_id,
            a.subject_id,
            a.attendance_date,
            a.status,
            s.department,
            s.semester
        FROM attendance a
        INNER JOIN students s ON s.student_id = a.student_id
        WHERE 1 = 1
    """
    params: dict = {}

    if since is not None:
        query += " AND a.attendance_date >= :since"
        params["since"] = since

    if student_ids:
        placeholders = ", ".join(f":sid{i}" for i in range(len(student_ids)))
        query += f" AND a.student_id IN ({placeholders})"
        params.update({f"sid{i}": sid for i, sid in enumerate(student_ids)})

    query += " ORDER BY a.student_id, a.attendance_date"

    with engine.connect() as conn:
        return pd.read_sql(text(query), conn, params=params, parse_dates=["attendance_date"])


def load_students(student_ids: list[int] | None = None) -> pd.DataFrame:
    """Student roster (id, roll number, name, department, semester)."""
    engine = get_engine()

    query = "SELECT student_id, roll_number, student_name, department, semester FROM students"
    params: dict = {}

    if student_ids:
        placeholders = ", ".join(f":sid{i}" for i in range(len(student_ids)))
        query += f" WHERE student_id IN ({placeholders})"
        params = {f"sid{i}": sid for i, sid in enumerate(student_ids)}

    with engine.connect() as conn:
        return pd.read_sql(text(query), conn, params=params)
