#!/usr/bin/env bash

DIR=$(dirname "$(realpath "$0")")

"${DIR}"/console update-chars --log --hide-details
"${DIR}"/console update-player-groups --log --hide-details
