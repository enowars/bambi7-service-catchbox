from enochecker_async import BaseChecker, BrokenServiceException, create_app, OfflineException, ELKFormatter, CheckerTaskMessage
import random, string, logging , sys, random, string, json, aiohttp

def generate_noise(amount):
    return "".join(random.choice(string.ascii_letters + string.digits) for _ in range(amount))

class CatchboxChecker(BaseChecker):
    port = 8004
    flag_count = 1
    noise_count = 0
    havoc_count = 1
    service_name = "catchbox"

    def putflag(self, logger, task, collection) -> None:
        self.flagfile = "uploads/flag-" + generate_noise(32)
        async with aiohttp.ClientSession(raise_for_status=True) as session:
            try:
                await session.post(f"http://{task.address}:{self.port}/index.php", data={"action":"upload", "name":self.flagfile, "content":self.flag})
            except:
                raise BrokenServiceException("Failed to upload flag file")

    def getflag(self, logger, task, collection) -> None:
        async with aiohttp.ClientSession(raise_for_status=True) as session:
            try:
                response = await session.get(f"http://{task.address}:{self.port}/view.php?f={self.flagfile}")
            except:
                raise BrokenServiceException("Request to view flag file failed")

            if self.flag not in response.text():
                raise BrokenServiceException("Flag file content changed")

    def havoc(self, logger, task, collection) -> None:
        async with aiohttp.ClientSession(raise_for_status=True) as session:
            try:
                response = await session.get(f"http://{task.address}:{self.port}/index.php", data={"action":"login", "username":"admin", "password":"test"})
            except:
                raise BrokenServiceException("Flag ")

            if "logged in (admin)" not in response.text:
                raise BrokenServiceException("Failed to login")

    def __init__(self):
        super(CatchboxChecker, self).__init__("Catchbox", 8080, 1, 0, 0)

logger = logging.getLogger()
handler = logging.StreamHandler(sys.stdout)
handler.setFormatter(ELKFormatter("%(message)s")) #ELK-ready output
logger.addHandler(handler)
logger.setLevel(logging.DEBUG)

app = create_app(CatchboxChecker())
