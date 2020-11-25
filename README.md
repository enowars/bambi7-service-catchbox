## CatchBox

### General

- Can upload files, but viewing checks session cookie for authentication

- Show files with last mod, such that you can automate flag grabbing and only look at most recent

- Single user "admin" to exploit

### VULNS

1. session cookie value is generated based on last login time, which is shown on "users" page

2. php code checks if file specified by username has the password specified. Username is not escaped correctly, such that we make the php script read a file we uploaded, whose content is known.

