import chess
from src.puzzle import validate_position


def test_position_is_valid():
    fen = '6k1/5ppp/8/8/8/5Q2/5PPP/6K1 w - - 0 1'
    result = validate_position(fen)
    assert result['is_valid'] is True
    assert result['legal_move_count'] > 0


def test_expected_move_is_checked_for_legality():
    fen = '6k1/5ppp/8/8/8/5Q2/5PPP/6K1 w - - 0 1'
    result = validate_position(fen, 'f3f7')
    assert result['expected_move_legal'] is True
