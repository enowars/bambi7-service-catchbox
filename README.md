## CatchBox

Simple file upload service.

Flag user- and filenames are given as attack info.

Flagstore 1 is stored in a file from the flag user.

Flagstore 2 is stored in a report created by the flag user.

### VULNS

1. upload file directory name is generated based on creation time
   (can be bruteforced using user creation time)

2. upload filename path traversal allows for reading reports and uploads
   (filename check is fumbled via php type juggling and wrong variable use)

3. nginx uploads alias allows arbitrary file read in /service (except database)

4. theoretically hash collision on flag user's name allows accessing report,
   but computation complexity is too high even for given round time
   (easy to find two strings with same md5 hash, but difficult to find
   'complement' for a given string)

