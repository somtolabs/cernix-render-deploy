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

    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "total_scans": len(records),
        "approved_count": decisions.get("APPROVED", 0),
        "rejected_count": decisions.get("REJECTED", 0),
        "duplicate_count": decisions.get("DUPLICATE", 0),
        "approval_rate": daily_summary["approval_rate"],
        "duplicate_rate": daily_summary["duplicate_rate"],
        "rejection_rate": daily_summary["rejection_rate"],
        "risk_distribution": {
            "low": risk_counts.get("low", 0),
            "medium": risk_counts.get("medium", 0),
            "high": risk_counts.get("high", 0),
        },
        "high_risk_students": students,
        "suspicious_examiners": examiners,
        "suspicious_devices": devices,
        "suspicious_ips": ips,
        "daily_summary": daily_summary,
        "risk_summary": {
            "low": risk_counts.get("low", 0),
            "medium": risk_counts.get("medium", 0),
            "high": risk_counts.get("high", 0),
            "overall_level": overall_level(students, examiners, devices, ips),
        },
        "recommendations": daily_summary["recommendations"],
    }


def save_report_json(path: Path, report: dict) -> None:
    write_json(path, report)


def save_report_html(path: Path, report: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(render_html(report), encoding="utf-8")


def render_html(report: dict) -> str:
    metrics = [
        ("Total scans", report.get("total_scans", 0)),
        ("Approved", report.get("approved_count", 0)),
        ("Rejected", report.get("rejected_count", 0)),
        ("Duplicate", report.get("duplicate_count", 0)),
        ("Approval rate", f"{report.get('approval_rate', 0)}%"),
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
    <p>Rule-based scan, examiner, device, IP, and payment/demo-mode risk analysis. Generated {escape(report.get("generated_at"))}.</p>
    <div class="grid">{''.join(metric_card(label, value) for label, value in metrics)}</div>
  </header>
  <section>
    <h2>Risk Distribution</h2>
    {risk_distribution(report.get("risk_distribution", {}))}
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
    <h2>Suspicious Devices/IPs</h2>
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
    if score <= 30:
        return "low"
    if score <= 60:
        return "medium"
    return "high"


def metric_card(label: str, value: object) -> str:
    return f'<div class="metric"><span>{escape(label)}</span><b>{escape(value)}</b></div>'


def risk_distribution(dist: dict) -> str:
    return "<div class=\"grid\">" + "".join(
        metric_card(label.title(), dist.get(label, 0)) for label in ("low", "medium", "high")
    ) + "</div>"


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
        return '<div class="empty">No suspicious devices or IP addresses detected.</div>'
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
