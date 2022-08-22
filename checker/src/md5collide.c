#include "md5.h"

#include <sys/random.h>
#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

int
main(int argc, const char **argv)
{
	uint8_t outhash[32];
	uint8_t inhash[32];
	uint8_t buf[64];
	char linebuf[65];
	MD5_CTX ctx;
	int i;

	while (fgets(linebuf, sizeof(linebuf), stdin)) {
		if (strlen(linebuf) != 64)
			continue;
		for (i = 0; i < 32; i++)
			sscanf(linebuf + 2 * i, "%02hhX", &inhash[i]);

		assert(getrandom(buf, 64, 0) == 64);
		MD5Init(&ctx);
		MD5Update(&ctx, buf, 64);
		MD5Final(outhash, &ctx);

		if (!memcmp(outhash, inhash, 32)) {
			for (i = 0; i < 64; i++)
				printf("%02X", buf[i]);
			printf("\n");
			break;
		}
	}
}
