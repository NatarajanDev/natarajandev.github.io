from __future__ import annotations

import csv
import json
import sys
from decimal import Decimal, InvalidOperation
from pathlib import Path


def load_rows(path: Path) -> list[dict]:
    with path.open(newline='', encoding='utf-8') as handle:
        return list(csv.DictReader(handle))


def normalize(rows: list[dict]) -> list[dict]:
    output = []
    for line_no, row in enumerate(rows, start=2):
        name = (row.get('name') or '').strip()
        amount_text = (row.get('amount') or '').strip()
        if not name:
            raise ValueError(f'line {line_no}: name is required')
        try:
            amount = Decimal(amount_text)
        except InvalidOperation as exc:
            raise ValueError(f'line {line_no}: invalid amount') from exc
        if amount < 0:
            raise ValueError(f'line {line_no}: amount cannot be negative')
        output.append({'name': name, 'amount': str(amount.quantize(Decimal("0.01")))})
    return output


def build_report(rows: list[dict]) -> dict:
    total = sum((Decimal(r['amount']) for r in rows), Decimal('0'))
    return {
        'record_count': len(rows),
        'total_amount': str(total.quantize(Decimal('0.01'))),
        'records': rows,
    }


def main() -> int:
    if len(sys.argv) != 3:
        print('Usage: python -m src.process_csv INPUT.csv OUTPUT.json')
        return 2
    source, target = map(Path, sys.argv[1:])
    report = build_report(normalize(load_rows(source)))
    target.write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(f'Wrote {report["record_count"]} records to {target}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
