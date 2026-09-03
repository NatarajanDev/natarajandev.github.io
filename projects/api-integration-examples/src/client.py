from __future__ import annotations

import json
import time
from dataclasses import dataclass
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


class ApiError(RuntimeError):
    pass


@dataclass
class ApiClient:
    base_url: str
    timeout: float = 5.0
    retries: int = 2

    def get_json(self, path: str) -> dict:
        url = self.base_url.rstrip('/') + '/' + path.lstrip('/')
        last_error: Exception | None = None

        for attempt in range(self.retries + 1):
            try:
                request = Request(url, headers={'Accept': 'application/json'})
                with urlopen(request, timeout=self.timeout) as response:
                    if response.status != 200:
                        raise ApiError(f'HTTP {response.status}')
                    payload = json.loads(response.read().decode('utf-8'))
                    if not isinstance(payload, dict):
                        raise ApiError('Expected a JSON object')
                    return payload
            except (HTTPError, URLError, json.JSONDecodeError, ApiError) as exc:
                last_error = exc
                if attempt < self.retries:
                    time.sleep(0.25 * (2 ** attempt))

        raise ApiError(f'API request failed: {last_error}') from last_error


if __name__ == '__main__':
    client = ApiClient('https://httpbin.org')
    print(client.get_json('/json'))
