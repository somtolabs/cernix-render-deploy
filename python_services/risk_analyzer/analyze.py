#!/usr/bin/env python3
"""Command-line entry point for the CERNIX Intelligence Module."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from models import ScanRecord
from report import build_report, save_report_html, save_report_json
from utils import load_scan_logs


def parse_args(argv: list[str]) -> argparse.Namespace:
    base_dir = Path(__file__).resolve().parent
    parser = argparse.ArgumentParser(description="Analyze exported CERNIX scan logs.")
    parser.add_argument("input", nargs="?", default=str(base_dir / "sample_input.json"), help="Input JSON file.")
    parser.add_argument("output", nargs="?", default=str(base_dir / "sample_output.json"), help="Output JSON report.")
    parser.add_argument("--html", default=str(base_dir / "sample_report.html"), help="Optional HTML report path.")
    return parser.parse_args(argv)


def main(argv: list[str]) -> int:
    args = parse_args(argv)
    input_path = Path(args.input)
    output_path = Path(args.output)
    html_path = Path(args.html) if args.html else None

    try:
        raw_logs = load_scan_logs(input_path)
    except (FileNotFoundError, ValueError) as exc:
        print(f"CERNIX Intelligence Module error: {exc}", file=sys.stderr)
        return 1

    records = [ScanRecord.from_dict(row) for row in raw_logs]
    report = build_report(records)
    save_report_json(output_path, report)

    if html_path:
        save_report_html(html_path, report)

    print(
        "CERNIX Intelligence Module: "
        f"{report['total_scans']} scans, "
        f"{report['approved_count']} approved, "
        f"{report['rejected_count']} rejected, "
        f"{report['duplicate_count']} duplicate, "
        f"overall risk {report['risk_summary']['overall_level']}."
    )
    print(f"JSON report: {output_path}")
    if html_path:
        print(f"HTML report: {html_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
