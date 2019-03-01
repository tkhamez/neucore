#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

${DIR}/console update-chars
${DIR}/console update-player-groups
${DIR}/console check-tokens
${DIR}/console update-member-tracking
${DIR}/console send-account-disabled-mail
