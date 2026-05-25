"""Utility helpers for the CERNIX Intelligence Module."""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def parse_timestamp(value: Any) -> datetime | None:
    """Parse common ISO-like timestamps without throwing on bad input."""

    if not value:
        return None

    text = str(value).strip()
    if text.endswith("Z"):
        text = text[:-1] + "+00:00"

    for candidate in (text, text.replace(" ", "T")):
        try:
            parsed = datetime.fromisoformat(candidate)
            if parsed.tzinfo is None:
                parsed = parsed.replace(tzinfo=timezone.utc)
            return parsed
        except ValueError:
            continue

    return None


def normalize_decision(value: Any) -> str:
    return str(value or "UNKNOWN").strip().upper()


def normalize_status(value: Any) -> str:
    return str(value or "unknown").strip().lower()


def risk_level(score: int) -> str:
    if score >= 75:
        return "critical"
    if score >= 50:
        return "high"
    if score >= 25:
        return "medium"
    return "low"


def percentage(part: int, total: int) -> float:
    if total <= 0:
        return 0.0
    return round((part / total) * 100, 2)


def mask_rrr(value: Any) -> str | None:
    """Mask Remita/demo RRR values before they appear in reports."""

    if value is None:
        return None

    rrr = str(value).strip()
    if not rrr:
        return None

    if rrr.upper().startswith("TEST-"):
        return "TEST-****"

    if len(rrr) <= 4:
        return "*" * len(rrr)

    return ("*" * (len(rrr) - 4)) + rrr[-4:]


def safe_float(value: Any) -> float | None:
    try:
        if value is None or value == "":
            return None
        return float(value)
    except (TypeError, ValueError):
        return None


def first_non_empty(rows: list[Any], attr: str) -> Any:
    for row in rows:
        value = getattr(row, attr, None)
        if value:
            return value
    return None


def load_scan_logs(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        raise FileNotFoundError(f"Input file not found: {path}")

    with path.open("r", encoding="utf-8") as handle:
        payload = json.load(handle)

    if isinstance(payload, list):
        rows = payload
    elif isinstance(payload, dict):
        rows = payload.get("scan_logs", [])
    else:
        rows = []

    return [row for row in rows if isinstance(row, dict)]


def write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as handle:
        json.dump(payload, handle, indent=2, ensure_ascii=False)
        handle.write("\n")
