## CatchBox

Simple file upload service.

### VULNS

1. session cookie value is generated based on user creation time, which is shown on "users" page

2. path traversal using file name allows downloading (truncated) error log where credentials are logged

3. path traversal using nginx misconfiguration allows downloading sqlite db
