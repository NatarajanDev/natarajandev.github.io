import json
from pathlib import Path

from src.process_csv import build_report, load_rows, normalize


def test_normalize_and_report(tmp_path: Path):
    source = tmp_path / 'input.csv'
    source.write_text('name,amount\nAlpha,10.50\nBeta,4.25\n', encoding='utf-8')
    rows = normalize(load_rows(source))
    report = build_report(rows)
    assert report['record_count'] == 2
    assert report['total_amount'] == '14.75'


def test_invalid_amount(tmp_path: Path):
    source = tmp_path / 'input.csv'
    source.write_text('name,amount\nAlpha,nope\n', encoding='utf-8')
    rows = load_rows(source)
    try:
        normalize(rows)
    except ValueError as exc:
        assert 'invalid amount' in str(exc)
    else:
        raise AssertionError('Expected ValueError')
