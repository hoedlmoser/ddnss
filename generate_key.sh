#!/bin/bash

echo $1 | tr [:upper:] [:lower:] | openssl md5 | sed "s/^.*= *//" | openssl base64

