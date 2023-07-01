#!/usr/bin/env sh

#DIR=$(dirname "$(readlink -f "$0")")
DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

"${DIR}"/console update-corporations --log --hide-details
"${DIR}"/console update-chars --log --hide-details
"${DIR}"/console update-player-groups --log --hide-details
"${DIR}"/console update-service-accounts --log --hide-details

"${DIR}"/console update-member-tracking --log
"${DIR}"/console send-missing-character-mail --log

"${DIR}"/console check-tokens --log --hide-details
"${DIR}"/console send-invalid-token-mail --log

"${DIR}"/console auto-allowlist --log --hide-details
"${DIR}"/console clean-http-cache --log
