"""Report assembly and HTML rendering for CERNIX intelligence output."""

from __future__ import annotations

import html
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

from models import ScanRecord
from rules import analyze_endpoints, analyze_examiners, analyze_students, build_daily_summary
from utils import write_json


def build_report(records: list[ScanRecord]) -> dict:
    decisions = Counter(row.decision for row in records)
    students = analyze_students(records)
    examiners = analyze_examiners(records)
    devices = analyze_endpoints(records, "device_fp", "device")
    ips = analyze_endpoints(records, "ip_address", "ip")
    risk_counts = Counter(item["risk_level"] for item in students)
    risk_counts.update(item["risk_level"] for item in examiners)
    risk_counts.update(item["risk_level"] for item in devices)
    risk_counts.update(item["risk_level"] for item in ips)
    daily_summary = build_daily_summary(records, students, examiners, devices, ips)

    summary = {
        "total_scans": len(records),
        "approved_count": decisions.get("APPROVED", 0),
        "rejected_count": decisions.get("REJECTED", 0),
        "duplicate_count": decisions.get("DUPLICATE", 0),
        "approval_rate": daily_summary["approval_rate"],
        "duplicate_rate": daily_summary["duplicate_rate"],
        "rejection_rate": daily_summary["rejection_rate"],
        "total_students": len({row.matric_no for row in records if row.matric_no != "unknown"}),
        "verified_payments": sum(1 for row in records if row.payment_status in {"verified", "verified demo payment", "payment successful"}),
        "qr_issued": len({row.token_id for row in records if row.token_id != "unknown"}),
    }
    risk_overview = {
        "critical_risk_students_count": sum(1 for item in students if item.get("risk_level") == "critical"),
        "high_risk_students_count": sum(1 for item in students if item.get("risk_level") == "high"),
        "medium_risk_students_count": sum(1 for item in students if item.get("risk_level") == "medium"),
        "suspicious_examiners_count": len(examiners),
        "suspicious_devices_count": len(devices),
        "suspicious_ips_count": len(ips),
        "duplicate_attempts": summary["duplicate_count"],
        "rejected_attempts": summary["rejected_count"],
    }
    department_trends = build_trends(records, "department")
    level_trends = build_trends(records, "level")

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "source": "python_risk_analyzer",
        "summary": summary,
        "risk_overview": risk_overview,
        "risk_distribution": {
            "low": risk_counts.get("low", 0),
            "medium": risk_counts.get("medium", 0),
            "high": risk_counts.get("high", 0),
            "critical": risk_counts.get("critical", 0),
        },
        "department_trends": department_trends,
        "level_trends": level_trends,
        "key_observations": daily_summary["key_observations"],
        "student_risks": students,
        "high_risk_students": students,
        "suspicious_examiners": examiners,
        "suspicious_devices": devices,
        "suspicious_ips": ips,
        "daily_summary": daily_summary,
        "risk_summary": {
            "low": risk_counts.get("low", 0),
            "medium": risk_counts.get("medium", 0),
            "high": risk_counts.get("high", 0),
            "critical": risk_counts.get("critical", 0),
            "overall_level": overall_level(students, examiners, devices, ips),
        },
        "recommendations": daily_summary["recommendations"],
    }

    # Backward-compatible top-level summary values for older Laravel readers.
    report.update(summary)

    return report


def save_report_json(path: Path, report: dict) -> None:
    write_json(path, report)


def save_report_html(path: Path, report: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(render_html(report), encoding="utf-8")


def render_html(report: dict) -> str:
    summary = report.get("summary", report)
    metrics = [
        ("Total scans", summary.get("total_scans", 0)),
        ("Approved", summary.get("approved_count", 0)),
        ("Rejected", summary.get("rejected_count", 0)),
        ("Repeated", summary.get("duplicate_count", 0)),
        ("Approval rate", f"{summary.get('approval_rate', 0)}%"),
        ("Overall risk", report.get("risk_summary", {}).get("overall_level", "low").title()),
    ]

    return f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CERNIX Intelligence Report</title>
  <style>
    :root {{ color-scheme: light; --ink:#111827; --muted:#667085; --line:#e5e7eb; --navy:#0f2347; --soft:#f8fafc; }}
    body {{ margin:0; font-family: Arial, Helvetica, sans-serif; background:#f4f6f8; color:var(--ink); }}
    main {{ max-width: 1040px; margin: 0 auto; padding: 32px 18px; }}
    header {{ background:white; border:1px solid var(--line); border-radius:16px; padding:22px; margin-bottom:18px; }}
    h1 {{ margin:0; color:var(--navy); font-size:28px; }}
    h2 {{ margin:0 0 12px; color:var(--navy); font-size:18px; }}
    p {{ color:var(--muted); line-height:1.5; }}
    .grid {{ display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-top:18px; }}
    .metric, section {{ background:white; border:1px solid var(--line); border-radius:14px; padding:16px; }}
    .metric span {{ display:block; color:var(--muted); font-size:12px; font-weight:700; text-transform:uppercase; }}
    .metric b {{ display:block; margin-top:8px; font-size:24px; color:var(--navy); }}
    section {{ margin-top:16px; }}
    table {{ width:100%; border-collapse:collapse; font-size:14px; }}
    th, td {{ text-align:left; border-bottom:1px solid var(--line); padding:9px 8px; vertical-align:top; }}
    th {{ color:var(--muted); font-size:12px; text-transform:uppercase; }}
    .pill {{ display:inline-block; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:700; background:#eef2ff; color:#1e3a8a; }}
    ul {{ margin:8px 0 0 18px; padding:0; }}
    li {{ margin:6px 0; }}
    .empty {{ color:var(--muted); background:var(--soft); border-radius:10px; padding:12px; }}
  </style>
</head>
<body>
<main>
  <header>
    <h1>CERNIX Intelligence Report</h1>
    <p>Rule-based scan, examiner, scanner, network, and payment/demo-mode risk analysis. Generated {escape(report.get("generated_at"))}.</p>
    <div class="grid">{''.join(metric_card(label, value) for label, value in metrics)}</div>
  </header>
  <section>
    <h2>Risk Distribution</h2>
    {risk_distribution(report.get("risk_distribution", {}))}
  </section>
  <section>
    <h2>Department Trends</h2>
    {trend_table(report.get("department_trends", []), "Department")}
  </section>
  <section>
    <h2>Level Trends</h2>
    {trend_table(report.get("level_trends", []), "Level")}
  </section>
  <section>
    <h2>High-Risk Students</h2>
    {student_table(report.get("high_risk_students", []))}
  </section>
  <section>
    <h2>Suspicious Examiners</h2>
    {examiner_table(report.get("suspicious_examiners", []))}
  </section>
  <section>
    <h2>Scanner And Network Patterns</h2>
    {endpoint_table(report.get("suspicious_devices", []) + report.get("suspicious_ips", []))}
  </section>
  <section>
    <h2>Recommendations</h2>
    {list_block(report.get("recommendations", []))}
  </section>
</main>
</body>
</html>
"""


def overall_level(*groups: list[dict]) -> str:
    score = 0
    for group in groups:
        for item in group:
            score = max(score, int(item.get("score") or item.get("suspicious_score") or 0))
    if score >= 75:
        return "critical"
    if score >= 50:
        return "high"
    if score >= 25:
        return "medium"
    return "low"


def metric_card(label: str, value: object) -> str:
    return f'<div class="metric"><span>{escape(label)}</span><b>{escape(value)}</b></div>'


def risk_distribution(dist: dict) -> str:
    return "<div class=\"grid\">" + "".join(
        metric_card(label.title(), dist.get(label, 0)) for label in ("low", "medium", "high", "critical")
    ) + "</div>"


def build_trends(records: list[ScanRecord], attr: str) -> list[dict]:
    grouped: dict[str, list[ScanRecord]] = {}
    for record in records:
        label = getattr(record, attr) or "Unknown"
        grouped.setdefault(str(label), []).append(record)

    trends = []
    for label, rows in grouped.items():
        decisions = Counter(row.decision for row in rows)
        total = len(rows)
        duplicate = decisions.get("DUPLICATE", 0)
        rejected = decisions.get("REJECTED", 0)
        risk_score = (duplicate * 20) + (rejected * 15)
        trends.append({
            "label": label,
            "total_scans": total,
            "approved_count": decisions.get("APPROVED", 0),
            "rejected_count": rejected,
            "duplicate_count": duplicate,
            "approval_rate": round((decisions.get("APPROVED", 0) / total) * 100, 2) if total else 0.0,
            "duplicate_rate": round((duplicate / total) * 100, 2) if total else 0.0,
            "rejection_rate": round((rejected / total) * 100, 2) if total else 0.0,
            "risk_score": risk_score,
        })

    return sorted(trends, key=lambda item: (item["risk_score"], item["total_scans"]), reverse=True)


def trend_table(rows: list[dict], label: str) -> str:
    if not rows:
        return f'<div class="empty">No {escape(label.lower())} trend data available.</div>'
    body = "".join(
        "<tr>"
        f"<td>{escape(row.get('label'))}</td>"
        f"<td>{escape(row.get('total_scans'))}</td>"
        f"<td>{escape(row.get('approved_count'))}</td>"
        f"<td>{escape(row.get('rejected_count'))}</td>"
        f"<td>{escape(row.get('duplicate_count'))}</td>"
        f"<td>{escape(row.get('duplicate_rate'))}%</td>"
        f"<td>{escape(row.get('rejection_rate'))}%</td>"
        "</tr>"
        for row in rows
    )
    return f"<table><thead><tr><th>{escape(label)}</th><th>Scans</th><th>Approved</th><th>Rejected</th><th>Repeated</th><th>Repeated rate</th><th>Rejection rate</th></tr></thead><tbody>{body}</tbody></table>"


def student_table(rows: list[dict]) -> str:
    if not rows:
        return '<div class="empty">No medium/high-risk students detected.</div>'
    body = "".join(
        "<tr>"
        f"<td>{escape(row.get('matric_no'))}<br><small>{escape(row.get('student_name'))}</small></td>"
        f"<td>{escape(row.get('department'))}<br><small>{escape(row.get('level'))}</small></td>"
        f"<td><span class=\"pill\">{escape(row.get('risk_level'))}</span><br>{escape(row.get('score'))}</td>"
        f"<td>{escape('; '.join(row.get('reasons', [])))}</td>"
        f"<td>{escape(row.get('recommendation'))}</td>"
        "</tr>"
        for row in rows
    )
    return f"<table><thead><tr><th>Student</th><th>Class</th><th>Risk</th><th>Reasons</th><th>Recommendation</th></tr></thead><tbody>{body}</tbody></table>"


def examiner_table(rows: list[dict]) -> str:
    if not rows:
        return '<div class="empty">No suspicious examiner activity detected.</div>'
    body = "".join(
        "<tr>"
        f"<td>{escape(row.get('examiner_id'))}<br><small>{escape(row.get('examiner_name'))}</small></td>"
        f"<td>{escape(row.get('total_scans'))}</td>"
        f"<td>{escape(row.get('approved_count'))}/{escape(row.get('rejected_count'))}/{escape(row.get('duplicate_count'))}</td>"
        f"<td><span class=\"pill\">{escape(row.get('risk_level'))}</span><br>{escape(row.get('suspicious_score'))}</td>"
        f"<td>{escape('; '.join(row.get('reasons', [])))}</td>"
        f"<td>{escape(row.get('recommendation'))}</td>"
        "</tr>"
        for row in rows
    )
    return f"<table><thead><tr><th>Examiner</th><th>Scans</th><th>A/R/D</th><th>Risk</th><th>Reasons</th><th>Recommendation</th></tr></thead><tbody>{body}</tbody></table>"


def endpoint_table(rows: list[dict]) -> str:
    if not rows:
        return '<div class="empty">No scanner or network risk pattern detected.</div>'
    body = "".join(
        "<tr>"
        f"<td>{escape(row.get('type'))}</td>"
        f"<td>{escape(row.get('identifier'))}</td>"
        f"<td>{escape(row.get('total_scans'))}</td>"
        f"<td>{escape(row.get('unique_students'))}</td>"
        f"<td>{escape(row.get('unique_examiners'))}</td>"
        f"<td><span class=\"pill\">{escape(row.get('risk_level'))}</span></td>"
        f"<td>{escape(row.get('recommendation'))}</td>"
        "</tr>"
        for row in rows
    )
    return f"<table><thead><tr><th>Type</th><th>Identifier</th><th>Scans</th><th>Students</th><th>Examiners</th><th>Risk</th><th>Recommendation</th></tr></thead><tbody>{body}</tbody></table>"


def list_block(items: list[str]) -> str:
    if not items:
        return '<div class="empty">No recommendations.</div>'
    return "<ul>" + "".join(f"<li>{escape(item)}</li>" for item in items) + "</ul>"


def escape(value: object) -> str:
    return html.escape("" if value is None else str(value))
