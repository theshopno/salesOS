#!/bin/bash
# Watches for finished call recordings and pre-transcodes them to MP3 so the
# CRM's first play is instant (no on-demand transcode wait). Deliberately
# decoupled from the Asterisk dialplan (inotify on the filesystem, not a
# dialplan hangup hook) so it can be deployed without touching
# extensions.conf while it's under active concurrent editing.
#
# Cache lives outside RECORDINGS_ROOT on purpose: backup_recordings.sh
# rclone-syncs RECORDINGS_ROOT to Google Drive every 15 minutes, and the
# MP3s are disposable derivatives, not archival data - keeping them out of
# that tree avoids paying cloud storage/bandwidth twice for every call.

RECORDINGS_ROOT=/var/spool/asterisk/recordings
MP3_CACHE_ROOT=/var/spool/asterisk/mp3_cache
LOG=/var/log/asterisk/mp3-cache-watcher.log

mkdir -p "$MP3_CACHE_ROOT"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOG"
}

transcode() {
    local gsm="$1"
    local relpath="${gsm#$RECORDINGS_ROOT/}"
    local mp3="$MP3_CACHE_ROOT/${relpath%.gsm}.mp3"
    local mp3dir
    mp3dir="$(dirname "$mp3")"

    [ -f "$mp3" ] && return 0
    mkdir -p "$mp3dir"

    local tmp="$mp3.$$.tmp"
    # -f mp3 required: the temp name ends in ".tmp", not ".mp3", so ffmpeg
    # can't guess the container format from the extension.
    if /usr/bin/ffmpeg -y -loglevel error -i "$gsm" -ac 1 -b:a 48k -f mp3 "$tmp" 2>>"$LOG"; then
        mv "$tmp" "$mp3"
        log "cached: $relpath"
    else
        rm -f "$tmp"
        log "FAILED: $relpath"
    fi
}

log "watcher started"

# close_write catches the outbound flow (recorded straight into its final
# AgentName folder, MixMonitor closes the file at hangup). moved_to catches
# the queue/inbound flow (recorded into _pending/, then System(mv) renames
# it into the final AgentName folder at hangup - a rename fires no
# close_write on the destination, only moved_to).
inotifywait -m -r -e close_write -e moved_to --format '%w%f' "$RECORDINGS_ROOT" 2>>"$LOG" | \
while read -r file; do
    case "$file" in
        *.gsm)
            [[ "$file" == *"/_pending/"* ]] && continue   # not yet in its final AgentName folder
            transcode "$file"
            ;;
    esac
done
