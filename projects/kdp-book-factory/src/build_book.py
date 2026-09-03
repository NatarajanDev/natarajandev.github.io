from __future__ import annotations

import argparse
import json
from pathlib import Path

from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import getSampleStyleSheet
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer


def build_pdf(metadata: dict, output: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    styles = getSampleStyleSheet()
    story = [
        Paragraph(metadata['title'], styles['Title']),
        Paragraph(metadata.get('subtitle', ''), styles['Heading2']),
        Spacer(1, 18),
    ]
    for section in metadata.get('sections', []):
        story.append(Paragraph(section['heading'], styles['Heading1']))
        story.append(Paragraph(section['body'], styles['BodyText']))
        story.append(Spacer(1, 12))
    SimpleDocTemplate(str(output), pagesize=letter, title=metadata['title']).build(story)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('metadata', type=Path)
    parser.add_argument('output', type=Path)
    args = parser.parse_args()
    metadata = json.loads(args.metadata.read_text(encoding='utf-8'))
    build_pdf(metadata, args.output)
    print(f'Generated {args.output}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
