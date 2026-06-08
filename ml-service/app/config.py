"""Centralised settings, loaded from environment variables / `.env`."""
from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # MySQL read replica — analytical/training queries are read-only and must
    # never compete with the primary's write traffic. Mirrors the `read`/`write`
    # split already configured in api/config/database.php. Falls back to the
    # primary host/port when no replica is set (e.g. local dev / docker-compose
    # where DB_READ_HOST defaults to the primary).
    db_read_host: str = "127.0.0.1"
    db_read_port: int = 3306
    db_database: str = "attendance_db"
    db_username: str = "root"
    db_password: str = ""

    # Must match api/config/attendance.php's `detention_threshold` so the
    # training labels agree with how Laravel computes detention.
    detention_threshold: float = 75.0

    model_dir: str = "trained_models"
    model_version: str = "detention_v1"


@lru_cache
def get_settings() -> Settings:
    return Settings()
