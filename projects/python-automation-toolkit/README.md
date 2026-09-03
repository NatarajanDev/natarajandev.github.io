# Python Automation Toolkit

A small, testable CSV automation pipeline. It reads operational records, validates and normalizes them, calculates totals, and writes a JSON report.

## Run

```bash
python -m src.process_csv sample/input.csv sample/output.json
python -m pytest
```

## Design
- Pure transformation functions are easy to test.
- Invalid rows are rejected with explicit errors.
- The CLI keeps file I/O separate from business logic.
- Output is deterministic JSON for downstream workflows.

The sample data is synthetic.
