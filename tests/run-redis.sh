#!/bin/sh
Auth=""
TLS=""
for arg in "$@"; do
  case "$arg" in
    auth)
      Auth="--requirepass open-sesame"
      ;;
    tls)
      TLS="--tls-port 6379 --tls-cert-file ./redis.crt --tls-key-file ./redis.key --tls-ca-cert-file ./ca.crt --port 0"
      ;;
  esac
done
redis-server --loglevel verbose ${Auth} ${TLS}
