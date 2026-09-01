#!/bin/bash
# Deletes cached MP3s older than 30 days. Runs daily via cron.
#
# 30 days, not 90: this cache is a disposable speed optimization (re-transcode
# from the source .gsm is ~1-2s), not archival data - the .gsm originals are
# the source of truth and already have their own 90-day local retention
# (cleanup_recordings.sh) plus a permanent copy on Google Drive
# (backup_recordings.sh). A shorter TTL here just keeps the box from
# accumulating a second, redundant copy of every call indefinitely.
CACHEDIR=/var/spool/asterisk/mp3_cache
LOG=/var/log/asterisk/mp3-cache-cleanup.log

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting MP3 cache cleanup (>30 days)" >> "$LOG"
DELETED=$(find "$CACHEDIR" -type f -mtime +30 -print -delete | wc -l)
find "$CACHEDIR" -mindepth 1 -type d -empty -delete
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deleted $DELETED file(s)" >> "$LOG"
