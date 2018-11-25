#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

${DIR}/console update-chars
${DIR}/console send-account-disabled-mail
${DIR}/console update-player-groups
