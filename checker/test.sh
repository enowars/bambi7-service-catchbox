#!/bin/sh

fallback=$(ip addr | grep "scope global" | cut -d' ' -f6 | cut -d'/' -f1 | head -n1)
ADDRESS=${ADDRESS:-$fallback}

export ENOCHECKER_TEST_CHECKER_ADDRESS=$ADDRESS
export ENOCHECKER_TEST_CHECKER_PORT=9092
export ENOCHECKER_TEST_SERVICE_ADDRESS=$ADDRESS
export ENOCHECKER_TEST_SERVICE_PORT=9090

enochecker_test -vv $@
