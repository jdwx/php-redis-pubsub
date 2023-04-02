#!/bin/sh
redis-server \
	--tls-cert-file ./redis.crt \
	--tls-key-file ./redis.key \
	--tls-ca-cert-file ../ca/ca.crt \
	--tls-port 6379 \
	--port 0 \
	--loglevel verbose \
	--requirepass open-sesame

