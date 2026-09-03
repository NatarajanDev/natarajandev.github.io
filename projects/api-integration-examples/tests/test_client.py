from src.client import ApiClient, ApiError


def test_builds_url_and_parses_json(monkeypatch):
    class Response:
        status = 200
        def read(self):
            return b'{"ok": true}'
        def __enter__(self): return self
        def __exit__(self, *args): pass

    monkeypatch.setattr('src.client.urlopen', lambda request, timeout: Response())
    result = ApiClient('https://example.test').get_json('/health')
    assert result == {'ok': True}


def test_bad_json_is_reported(monkeypatch):
    class Response:
        status = 200
        def read(self): return b'not-json'
        def __enter__(self): return self
        def __exit__(self, *args): pass

    monkeypatch.setattr('src.client.urlopen', lambda request, timeout: Response())
    try:
        ApiClient('https://example.test', retries=0).get_json('/health')
    except ApiError as exc:
        assert 'API request failed' in str(exc)
    else:
        raise AssertionError('Expected ApiError')
