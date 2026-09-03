"""Compatibility entry point.

Main service entry point is `app.py`.
You can still run:
    uvicorn cpi_engine.api:app --host 0.0.0.0 --port 8000
"""
from app import app  # noqa: F401
