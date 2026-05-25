"""Rule-based intelligence for CERNIX exported logs."""

from __future__ import annotations

from collections import Counter, defaultdict

from models import ScanRecord
from utils import first_non_empty, percentage, risk_level


VERIFIED_PAYMENT_STATUSES = {"verified", "verified demo payment", "payment successful"}
EXPECTED_QR_STATUSES = {"UNUSED", "USED"}


def group_by(records: list[ScanRecord], attr: str) -> dict[str, list[ScanRecord]]:
    grouped: dict[str, list[ScanRecord]] = defaultdict(list)
    for record in records:
        grouped[str(getattr(record, attr) or "unknown")].append(record)
    return grouped


def analyze_students(records: list[ScanRecord]) -> list[dict]:
    findings: list[dict] = []

    for matric_no, rows in group_by(records, "matric_no").items():
        score = 0
        reasons: list[str] = []
        token_counts = Counter(row.token_id for row in rows if row.token_id != "unknown")
        duplicate_count = sum(1 for row in rows if row.decision == "DUPLICATE")
        rejected_count = sum(1 for row in rows if row.decision == "REJECTED")
        device_count = len({row.device_fp for row in rows if row.device_fp != "unknown"})
        ip_count = len({row.ip_address for row in rows if row.ip_address != "unknown"})
        payment_statuses = {row.payment_status for row in rows}
        statuses = {str(row.qr_status or row.token_status or "").upper() for row in rows if row.qr_status or row.token_status}
        short_interval = has_short_repeated_scans(rows)
        outside_window = any(is_outside_exam_window(row) for row in rows)

        if duplicate_count > 0 and "USED" in statuses:
            score += 40
            reasons.append("This exam pass was scanned again after approval")

        if duplicate_count >= 1 or any(count > 1 for count in token_counts.values()):
            score += 35
            reasons.append(f"{duplicate_count or 1} duplicate/repeated scan attempt(s)")

        if rejected_count >= 2:
            score += 25
            reasons.append("student has repeated rejected scans")

        if device_count >= 2:
            score += 20
            reasons.append("student appears on multiple device fingerprints")

        if ip_count >= 2:
            score += 20
            reasons.append("student appears from multiple IP addresses")

        if not payment_statuses or any(status not in VERIFIED_PAYMENT_STATUSES for status in payment_statuses):
            score += 20
            reasons.append("payment status is missing or not verified")

        if {status for status in statuses if status not in EXPECTED_QR_STATUSES}:
            score += 15
            reasons.append("exam pass status needs review")

        if outside_window:
            score += 15
            reasons.append("scan happened outside expected exam time")

        if short_interval:
            score += 15
            reasons.append("repeated scans happened within two minutes")

        if len(rows) >= 4:
            score += 10
            reasons.append("high scan attempt count for one exam access")

        if score > 0:
            example = rows[0]
            findings.append({
                "matric_no": matric_no,
                "student_name": first_non_empty(rows, "student_name"),
                "department": example.department,
                "level": example.level,
                "score": score,
                "risk_level": risk_level(score),
                "reasons": reasons,
                "recommendation": student_recommendation(score),
                "scan_count": len(rows),
                "duplicate_count": duplicate_count,
                "rejected_count": rejected_count,
                "device_count": device_count,
                "ip_count": ip_count,
                "last_activity": max((row.timestamp for row in rows if row.timestamp), default=None).isoformat()
                if any(row.timestamp for row in rows) else None,
            })

    return sorted(findings, key=lambda item: item["score"], reverse=True)


def analyze_examiners(records: list[ScanRecord]) -> list[dict]:
    grouped = group_by(records, "examiner_id")
    scan_counts = [len(rows) for key, rows in grouped.items() if key != "unknown"]
    average_scans = (sum(scan_counts) / len(scan_counts)) if scan_counts else 0
    findings: list[dict] = []

    for examiner_id, rows in grouped.items():
        if examiner_id == "unknown":
            continue

        decisions = Counter(row.decision for row in rows)
        approved = decisions.get("APPROVED", 0)
        rejected = decisions.get("REJECTED", 0)
        duplicate = decisions.get("DUPLICATE", 0)
        device_count = len({row.device_fp for row in rows if row.device_fp != "unknown"})
        ip_count = len({row.ip_address for row in rows if row.ip_address != "unknown"})
        burst = max_scans_in_window(rows, seconds=120)
        outside_window = sum(1 for row in rows if is_outside_exam_window(row))

        score = 0
        reasons: list[str] = []

        repeated_tokens = sum(1 for count in Counter(row.token_id for row in rows if row.token_id != "unknown").values() if count > 1)
        suspicious_students = len({row.matric_no for row in rows if row.decision in {"DUPLICATE", "REJECTED"} and row.matric_no != "unknown"})

        if duplicate >= 1:
            score += 30
            reasons.append("repeated scan attempts recorded")

        if rejected >= 2 or (len(rows) >= 4 and rejected / max(len(rows), 1) >= 0.5):
            score += 25
            reasons.append("rejected scan volume requires review")

        if repeated_tokens >= 1:
            score += 20
            reasons.append("same student/token scanned repeatedly")

        if burst >= 3:
            score += 15
            reasons.append("too many scans processed in a short time window")

        if average_scans and len(rows) > max(10, average_scans * 2):
            score += 20
            reasons.append("very high approval/scan volume compared to other examiners")

        if device_count > 2 or ip_count > 2:
            score += 10
            reasons.append("examiner appears across many devices or IP addresses")

        if suspicious_students >= 2:
            score += 15
            reasons.append("examiner is linked to multiple suspicious students")

        if outside_window:
            score += 15
            reasons.append("examiner has scans outside expected exam time windows")

        if score > 0:
            findings.append({
                "examiner_id": examiner_id,
                "examiner_name": first_non_empty(rows, "examiner_name"),
                "total_scans": len(rows),
                "approved_count": approved,
                "rejected_count": rejected,
                "duplicate_count": duplicate,
                "suspicious_score": score,
                "risk_level": risk_level(score),
                "reasons": reasons,
                "recommendation": examiner_recommendation(reasons),
                "max_two_minute_burst": burst,
                "device_count": device_count,
                "ip_count": ip_count,
                "suspicious_students_count": suspicious_students,
                "last_activity": max((row.timestamp for row in rows if row.timestamp), default=None).isoformat()
                if any(row.timestamp for row in rows) else None,
            })

    return sorted(findings, key=lambda item: item["suspicious_score"], reverse=True)


def analyze_endpoints(records: list[ScanRecord], attr: str, endpoint_type: str) -> list[dict]:
    findings: list[dict] = []

    for identifier, rows in group_by(records, attr).items():
        if identifier == "unknown":
            continue

        decisions = Counter(row.decision for row in rows)
        unique_students = len({row.matric_no for row in rows if row.matric_no != "unknown"})
        unique_examiners = len({row.examiner_id for row in rows if row.examiner_id != "unknown"})
        duplicate = decisions.get("DUPLICATE", 0)
        rejected = decisions.get("REJECTED", 0)
        score = 0
        reasons: list[str] = []

        if unique_students >= 3:
            score += 30
            reasons.append("identifier appears across many students")

        if rejected >= 2:
            score += 25
            reasons.append("identifier produced many rejected scans")

        if duplicate >= 1:
            score += 25
            reasons.append("scanner or network pattern produced many repeated scans")

        if unique_examiners > 1:
            score += 20
            reasons.append("identifier is linked to multiple examiners")

        if rejected + duplicate >= 4:
            score += 15
            reasons.append("repeated failed attempts from this identifier")

        if score > 0:
            findings.append({
                "identifier": identifier,
                "type": endpoint_type,
                "total_scans": len(rows),
                "unique_students": unique_students,
                "unique_examiners": unique_examiners,
                "duplicate_count": duplicate,
                "rejected_count": rejected,
                "score": score,
                "risk_level": risk_level(score),
                "reasons": reasons,
                "recommendation": endpoint_recommendation(endpoint_type),
            })

    return sorted(findings, key=lambda item: item["score"], reverse=True)


def build_daily_summary(
    records: list[ScanRecord],
    students: list[dict],
    examiners: list[dict],
    devices: list[dict],
    ips: list[dict],
) -> dict:
    decisions = Counter(row.decision for row in records)
    departments = Counter(row.department for row in records if row.department)
    levels = Counter(row.level for row in records if row.level)
    examiner_counts = Counter(row.examiner_id for row in records if row.examiner_id != "unknown")

    total = len(records)
    top_examiner_id, top_examiner_scans = examiner_counts.most_common(1)[0] if examiner_counts else (None, 0)
    top_examiner_name = None
    if top_examiner_id:
        top_examiner_name = first_non_empty([row for row in records if row.examiner_id == top_examiner_id], "examiner_name")

    summary = {
        "total_scans": total,
        "approved_count": decisions.get("APPROVED", 0),
        "rejected_count": decisions.get("REJECTED", 0),
        "duplicate_count": decisions.get("DUPLICATE", 0),
        "approval_rate": percentage(decisions.get("APPROVED", 0), total),
        "duplicate_rate": percentage(decisions.get("DUPLICATE", 0), total),
        "rejection_rate": percentage(decisions.get("REJECTED", 0), total),
        "most_active_department": most_common_label(departments),
        "most_active_level": most_common_label(levels),
        "top_examiner_by_scans": {
            "examiner_id": top_examiner_id,
            "examiner_name": top_examiner_name,
            "scan_count": top_examiner_scans,
        },
        "highest_risk_student": students[0] if students else None,
        "highest_risk_examiner": examiners[0] if examiners else None,
        "suspicious_device_count": len(devices),
        "suspicious_ip_count": len(ips),
        "key_observations": observations(records, students, examiners, devices, ips),
        "recommendations": recommendations(students, examiners, devices, ips),
    }

    return summary


def has_short_repeated_scans(rows: list[ScanRecord]) -> bool:
    return max_scans_in_window(rows, seconds=120) >= 2


def max_scans_in_window(rows: list[ScanRecord], seconds: int) -> int:
    timestamps = sorted(row.timestamp for row in rows if row.timestamp)
    if not timestamps:
        return 0

    max_count = 1
    left = 0
    for right, current in enumerate(timestamps):
        while (current - timestamps[left]).total_seconds() > seconds:
            left += 1
        max_count = max(max_count, right - left + 1)
    return max_count


def is_outside_exam_window(row: ScanRecord) -> bool:
    if not row.timestamp or not row.expected_start or not row.expected_end:
        return False
    return row.timestamp < row.expected_start or row.timestamp > row.expected_end


def most_common_label(counter: Counter) -> dict | None:
    if not counter:
        return None
    label, count = counter.most_common(1)[0]
    return {"label": label, "count": count}


def student_recommendation(score: int) -> str:
    if score >= 75:
        return "Investigate repeated access activity before this student is cleared."
    if score >= 50:
        return "Review this student's access activity before issuing a replacement pass."
    return "Monitor this student's next scan and confirm payment/access state."


def examiner_recommendation(reasons: list[str]) -> str:
    if any("duplicate" in reason for reason in reasons):
        return "Inspect repeated scan cluster and confirm scanner handling procedure."
    if any("device" in reason or "IP" in reason for reason in reasons):
        return "Confirm device assignment and check whether scanner access was shared."
    return "Review examiner activity log."


def endpoint_recommendation(endpoint_type: str) -> str:
    if endpoint_type == "device":
        return "Confirm whether this scanner device was shared or reused across halls."
    return "Review network/source IP context for repeated failed scan attempts."


def observations(
    records: list[ScanRecord],
    students: list[dict],
    examiners: list[dict],
    devices: list[dict],
    ips: list[dict],
) -> list[str]:
    if not records:
        return ["No scan records were supplied for analysis."]

    result: list[str] = []
    duplicate_departments = Counter(
        row.department for row in records if row.decision == "DUPLICATE" and row.department
    )
    if duplicate_departments:
        dept, count = duplicate_departments.most_common(1)[0]
        result.append(f"Repeated attempts were concentrated in {dept} ({count} repeated scans).")

    if devices:
        top = devices[0]
        result.append(f"Device {top['identifier']} appeared across {top['unique_students']} students.")

    if examiners:
        top = examiners[0]
        result.append(
            f"Examiner {top.get('examiner_id')} has a suspicious score of {top.get('suspicious_score')}."
        )

    if students:
        result.append(f"{len(students)} medium/high risk student record(s) require review.")
    else:
        result.append("No high-risk student activity detected.")

    if ips:
        top_ip = ips[0]
        result.append(f"IP {top_ip['identifier']} generated {top_ip['rejected_count']} rejected scans.")

    return result


def recommendations(students: list[dict], examiners: list[dict], devices: list[dict], ips: list[dict]) -> list[str]:
    if not any([students, examiners, devices, ips]):
        return ["No suspicious activity detected in the supplied data."]

    tips = [
        "Review high and medium risk findings before exam-day reporting is finalized.",
        "Compare flagged scans with physical hall attendance and examiner assignment.",
    ]

    if students:
        tips.append("Verify student payment/access records for flagged students.")
    if examiners:
        tips.append("Review examiner activity logs and scanner-device assignment.")
    if devices or ips:
        tips.append("Check repeated scanner or network patterns for shared-device behavior.")

    return tips
