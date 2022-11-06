## Catchbox

*The logical successor to Dropbox.*

### Flagstores

Flag user- and filenames are given as attack info.

Flagstore 1 is a file uploaded by the flag user.

Flagstore 2 is a report created by the flag user.

### Vulnerabilities

1. The random value used to generate the upload directory name is seeded
   using the time of the request, which can be inferred with minimal,
   online bruteforce from the user creation time in the `/users` endpoint.
   These can then be accessed using the public url e.g. `/uploads/<MD5>/<FILE>`.
   (flagstore 1)

2. The upload endpoint has a path traversal that allows reading of other
   users' reports and uploads. There is a type confusion between the return of
   strpos and false, which allows paths like "*/../*" to bypass the check
   since the index of ".." in the path component is 0 (== false). Additionally,
   the unsanitized parameter is stored in the database. This allows arbitrary
   read (not write) within `/reports`. The report filename is derived from
   the flaguser's username provided in the attack info. (flagstore 2)

3. The nginx `/uploads` alias allows for arbitrary file read in `/service`
   (except for the database), since `/upload../*` expands to `/service/files/../`
   and is not normalized again. This gives access to user reports as well as
   index.php, which can be used to steal / circumvent patches of other teams.
   (flagstore 2)

