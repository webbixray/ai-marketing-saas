# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_SANCTUM_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Authentication uses Laravel Sanctum. Include your API token as a Bearer token in the Authorization header: `Authorization: Bearer {token}`. You can retrieve your token by visiting your dashboard and generating an API token.
