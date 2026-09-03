from pathlib import Path

from src.build_book import build_pdf


def test_pdf_is_created(tmp_path):
    output = tmp_path / 'book.pdf'
    build_pdf(
        {'title': 'Test Book', 'subtitle': 'Demo', 'sections': [{'heading': 'One', 'body': 'Hello'}]},
        output,
    )
    assert output.exists()
    assert output.read_bytes().startswith(b'%PDF')
