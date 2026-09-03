# API Integration Examples

A dependency-light Python API client showing practical integration patterns: timeouts, retries with exponential backoff, JSON validation, and clear error handling.

The example targets a local/mock HTTP endpoint so no API key is required.

## Run

```bash
python src/client.py
```

## Why it matters
This is representative of the integration work used in business applications: consume a third-party API safely, normalize the response, and fail predictably when the service is unavailable.
