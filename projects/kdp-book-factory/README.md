# KDP Book Factory Sample

A small portfolio example of a document-generation pipeline: structured book metadata is converted into a print-ready PDF using ReportLab, followed by basic automated checks.

## Run

```bash
pip install -r requirements.txt
python src/build_book.py sample/book.json dist/sample-book.pdf
python -m pytest
```

## Pipeline

`metadata JSON -> content model -> PDF generation -> structural QA`

The sample demonstrates automation patterns rather than a full KDP submission bot. Final KDP upload and preview remain human-controlled.
