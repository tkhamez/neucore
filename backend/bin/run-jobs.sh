#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

${DIR}/console update-chars --log
${DIR}/console update-player-groups --log
${DIR}/console check-tokens --log
${DIR}/console update-member-tracking --log
${DIR}/console send-account-disabled-mail --log
