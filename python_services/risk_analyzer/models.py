"""Small data models for normalized CERNIX log records."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Any

from utils import mask_rrr, normalize_decision, normalize_status, parse_timestamp, safe_float


@dataclass(frozen=True)
class ScanRecord:
    student_id: str
    matric_no: str
    student_name: str | None
    department: str | None
    level: str | None
    examiner_id: str
    examiner_name: str | None
    decision: str
    token_id: str
    token_status: str | None
    qr_status: str | None
    device_fp: str
    ip_address: str
    timestamp: datetime | None
    payment_status: str
    rrr_number: str | None
    amount_confirmed: float | None
    session: str | None
    course_code: str | None
    course_title: str | None
    expected_start: datetime | None
    expected_end: datetime | None

    @classmethod
    def from_dict(cls, row: dict[str, Any]) -> "ScanRecord":
        matric = str(row.get("matric_no") or row.get("student_id") or "unknown").strip()
        student_id = str(row.get("student_id") or matric).strip()
        examiner_id = str(row.get("examiner_id") or "unknown").strip()

        return cls(
            student_id=student_id,
            matric_no=matric,
            student_name=clean(row.get("student_name")),
            department=clean(row.get("department")),
            level=clean(row.get("level")),
            examiner_id=examiner_id,
            examiner_name=clean(row.get("examiner_name")),
            decision=normalize_decision(row.get("decision")),
            token_id=str(row.get("token_id") or "unknown").strip(),
            token_status=clean(row.get("token_status") or row.get("qr_status")),
            qr_status=clean(row.get("qr_status") or row.get("token_status")),
            device_fp=str(row.get("device_fp") or "unknown").strip(),
            ip_address=str(row.get("ip_address") or "unknown").strip(),
            timestamp=parse_timestamp(row.get("timestamp")),
            payment_status=normalize_status(row.get("payment_status")),
            rrr_number=mask_rrr(row.get("rrr_number")),
            amount_confirmed=safe_float(row.get("amount_confirmed")),
            session=clean(row.get("session")),
            course_code=clean(row.get("course_code")),
            course_title=clean(row.get("course_title")),
            expected_start=parse_timestamp(row.get("expected_start") or row.get("exam_start")),
            expected_end=parse_timestamp(row.get("expected_end") or row.get("exam_end")),
        )


def clean(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None
