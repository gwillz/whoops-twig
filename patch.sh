#!/usr/bin/env bash
set -e
cd "$(dirname "$0")"

[[ -d vendor/filp/whoops ]] && rm -r vendor/filp/whoops
ln -s ../../../whoops vendor/filp/whoops

echo ok
