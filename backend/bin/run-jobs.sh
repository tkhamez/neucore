#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

${DIR}/console update-chars --log --hide-details
${DIR}/console update-player-groups --log --hide-details
${DIR}/console check-tokens --log --hide-details
${DIR}/console update-member-tracking --log
${DIR}/console send-invalid-token-mail --log
${DIR}/console send-missing-character-mail --log
#${DIR}/console auto-whitelist 1 --log
${DIR}/console clean-http-cache --log
