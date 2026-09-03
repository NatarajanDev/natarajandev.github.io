# Chess Puzzle Generator

A compact Python utility for validating chess positions and selecting a tactical move with Stockfish when the engine is installed.

## Pipeline

`FEN -> legality check -> legal move check -> optional Stockfish analysis -> JSON result`

This mirrors the validation-first approach used in automated chess-puzzle production. It intentionally keeps engine execution optional so the validator remains useful on a clean Python installation.

## Run

```bash
pip install -r requirements.txt
python src/puzzle.py sample/puzzle.json
```

For engine analysis, install Stockfish separately and pass `--engine /path/to/stockfish`.

## Important
The sample is a portfolio implementation, not a claim that a complete commercial puzzle factory is contained in this small example.
