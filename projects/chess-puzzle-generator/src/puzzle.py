from __future__ import annotations

import argparse
import json
from pathlib import Path

import chess


def validate_position(fen: str, expected_move: str | None = None) -> dict:
    board = chess.Board(fen)
    result = {
        'fen': fen,
        'is_valid': board.is_valid(),
        'turn': 'White' if board.turn == chess.WHITE else 'Black',
        'legal_move_count': board.legal_moves.count(),
    }
    if expected_move:
        move = chess.Move.from_uci(expected_move)
        result['expected_move_legal'] = move in board.legal_moves
    return result


def analyze_with_stockfish(fen: str, engine_path: str, depth: int = 18) -> dict:
    import chess.engine

    board = chess.Board(fen)
    engine = chess.engine.SimpleEngine.popen_uci(engine_path)
    try:
        info = engine.analyse(board, chess.engine.Limit(depth=depth))
        move = info['pv'][0]
        return {
            'best_move_uci': move.uci(),
            'best_move_san': board.san(move),
            'score': str(info['score'].pov(board.turn)),
            'depth': depth,
        }
    finally:
        engine.quit()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('input', type=Path)
    parser.add_argument('--engine')
    args = parser.parse_args()

    payload = json.loads(args.input.read_text(encoding='utf-8'))
    result = validate_position(payload['fen'], payload.get('expected_move'))
    if args.engine:
        result['engine'] = analyze_with_stockfish(payload['fen'], args.engine)
    print(json.dumps(result, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
