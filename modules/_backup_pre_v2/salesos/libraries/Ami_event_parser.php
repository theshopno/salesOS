<?php

/**
 * AMI Event Parser — used by ami_consumer.php (PHP CLI daemon, outside CI).
 * No CI dependencies. No static external calls.
 *
 * Input:  raw AMI key:value packet (array)
 * Output: normalized stream entry array ready for XADD, or null if filtered.
 *
 * Naming convention: all event_type values follow call.* / agent.* / queue.*
 * / recording.* / ai.* / notify.* as frozen in the Phase 2 architecture.
 *
 * Note: salesos:stream:webrtc must not carry raw SDP payloads.
 *       Only signaling metadata and routing information should be stored there.
 */
class Ami_event_parser
{
    private array $ext_map    = [];   // extension => [id, staff_id, fullname, extension]
    private array $call_cache = [];   // uniqueid  => call state snapshot (from Redis hashes)

    private string $pbx_id;
    private string $pbx_name;

    private static array $accepted = [
        'Newchannel', 'DialBegin', 'DialEnd',
        'BridgeEnter', 'BridgeLeave',
        'Hold', 'Unhold',
        'BlindTransfer', 'AttendedTransfer',
        'Hangup',
        'DeviceStateChange', 'PeerStatus',
        'QueueCallerJoin', 'QueueCallerLeave', 'QueueCallerAbandon',
        'AgentCalled', 'AgentConnect', 'AgentComplete', 'AgentRingNoAnswer',
        'QueueMemberStatus', 'QueueMemberPause',
        'MixMonitorStart', 'MixMonitorMute', 'MonitorStop',
    ];

    // Queue AMI status integer → human label
    private static array $queue_status_map = [
        0 => 'UNKNOWN', 1 => 'NOT_INUSE', 2 => 'INUSE',   3 => 'BUSY',
        4 => 'INVALID', 5 => 'UNAVAILABLE', 6 => 'RINGING',
        7 => 'RINGINUSE', 8 => 'ONHOLD',
    ];

    public function __construct(string $pbx_id, string $pbx_name)
    {
        $this->pbx_id   = $pbx_id;
        $this->pbx_name = $pbx_name;
    }

    public function set_extension_map(array $map): void
    {
        $this->ext_map = $map;
    }

    public function sync_call_state(string $uniqueid, array $hash): void
    {
        $this->call_cache[$uniqueid] = $hash;
    }

    public function drop_call_state(string $uniqueid): void
    {
        unset($this->call_cache[$uniqueid]);
    }

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Parse a raw AMI packet.
     * Returns normalized stream entry or null if the event is filtered.
     */
    public function parse(array $pkt): ?array
    {
        $name = $pkt['Event'] ?? '';
        if (!in_array($name, self::$accepted, true)) {
            return null;
        }

        return match ($name) {
            'Newchannel'        => $this->_newchannel($pkt),
            'DialBegin'         => $this->_dial_begin($pkt),
            'DialEnd'           => $this->_dial_end($pkt),
            'BridgeEnter'       => $this->_bridge_enter($pkt),
            'BridgeLeave'       => $this->_bridge_leave($pkt),
            'Hold'              => $this->_hold($pkt),
            'Unhold'            => $this->_unhold($pkt),
            'BlindTransfer'     => $this->_blind_transfer($pkt),
            'AttendedTransfer'  => $this->_attended_transfer($pkt),
            'Hangup'            => $this->_hangup($pkt),
            'DeviceStateChange' => $this->_device_state($pkt),
            'PeerStatus'        => $this->_peer_status($pkt),
            'QueueCallerJoin'   => $this->_queue_caller_join($pkt),
            'QueueCallerLeave'  => $this->_queue_caller_leave($pkt),
            'QueueCallerAbandon'=> $this->_queue_caller_abandon($pkt),
            'AgentCalled'       => $this->_agent_called($pkt),
            'AgentConnect'      => $this->_agent_connect($pkt),
            'AgentComplete'     => $this->_agent_complete($pkt),
            'AgentRingNoAnswer' => $this->_agent_ring_noanswer($pkt),
            'QueueMemberStatus' => $this->_queue_member_status($pkt),
            'QueueMemberPause'  => $this->_queue_member_pause($pkt),
            'MixMonitorStart'   => $this->_recording_started($pkt),
            'MixMonitorMute'    => $this->_recording_mute($pkt),
            'MonitorStop'       => $this->_recording_stopped($pkt),
            default             => null,
        };
    }

    /**
     * Determine which Redis stream an event_type belongs to.
     */
    public static function stream_for(string $event_type): string
    {
        return match (explode('.', $event_type)[0]) {
            'call'      => 'salesos:stream:calls',
            'agent'     => 'salesos:stream:agents',
            'queue'     => 'salesos:stream:queues',
            'recording' => 'salesos:stream:recordings',
            default     => 'salesos:stream:calls',
        };
    }

    // ── Envelope builder ──────────────────────────────────────────────────────

    /**
     * Build a flat string-keyed array suitable for XADD.
     * All values must be strings (Redis Streams requirement).
     */
    private function _env(string $event_type, string $uid, array $extra, array $pkt): array
    {
        $call    = $this->call_cache[$uid] ?? [];
        $session = $call['session_id']      ?? ($pkt['Linkedid'] ?? $uid);
        $convo   = $call['conversation_id'] ?? $session;

        $base = [
            'schema_ver'      => '1',
            'event_type'      => $event_type,
            'pbx_id'          => $this->pbx_id,
            'pbx_name'        => $this->pbx_name,
            'ts'              => sprintf('%.3f', microtime(true)),
            'call_uid'        => $uid,
            'session_id'      => $session,
            'conversation_id' => $convo,
            'call_id'         => $call['call_id']         ?? '',
            'from_state'      => $call['state']           ?? '',
            'to_state'        => '',
            'direction'       => $call['direction']        ?? '',
            'src'             => $pkt['CallerIDNum']      ?? ($call['src']         ?? ''),
            'dst'             => $pkt['Exten']            ?? ($call['dst']         ?? ''),
            'channel'         => $pkt['Channel']          ?? '',
            'bridge_id'       => $call['bridge_id']        ?? '',
            'queue_name'      => '',
            'hold_seq'        => $call['hold_count']       ?? '0',
            'xfer_seq'        => $call['xfer_count']       ?? '0',
            'agent_id'        => $call['agent_id']         ?? '',
            'agent_ext'       => $call['agent_ext']        ?? '',
            'agent_name'      => $call['agent_name']       ?? '',
            'staff_id'        => $call['staff_id']         ?? '',
            'entity_type'     => $call['entity_type']      ?? '',
            'entity_id'       => $call['entity_id']        ?? '',
            'entity_name'     => $call['entity_name']      ?? '',
            'webrtc'          => '',
            'ai_job_id'       => '',
            'targets'         => '[]',
            'raw_ami'         => json_encode($pkt),
        ];

        return array_merge($base, $extra);
    }

    // ── Call events ───────────────────────────────────────────────────────────

    private function _newchannel(array $pkt): array
    {
        $uid     = $pkt['Uniqueid']     ?? '';
        $linked  = $pkt['Linkedid']     ?? $uid;
        $src     = $pkt['CallerIDNum']  ?? '';
        $dst     = $pkt['Exten']        ?? '';
        $channel = $pkt['Channel']      ?? '';
        $context = $pkt['Context']      ?? '';

        $direction = $this->_direction($src, $dst, $context);
        $agent     = $this->_agent_chan($channel)
                  ?? $this->_agent_ext($dst)
                  ?? $this->_agent_ext($src);

        $extra = [
            'to_state'        => 'RINGING',
            'direction'       => $direction,
            'src'             => $src,
            'dst'             => $dst,
            'session_id'      => $linked,
            'conversation_id' => $linked,
        ];

        if ($agent) {
            $extra['agent_id']   = (string) $agent['id'];
            $extra['agent_ext']  = $agent['extension'];
            $extra['agent_name'] = $agent['fullname']  ?? '';
            $extra['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }

        $entry = $this->_env('call.ringing', $uid, $extra, $pkt);
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _dial_begin(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']          ?? '';
        $agent = $this->_agent_chan($pkt['Channel'] ?? '');

        $extra = [
            'to_state' => 'DIALING',
            'dial_dst' => $pkt['DestCallerIDNum'] ?? $pkt['DialString'] ?? '',
        ];

        $entry = $this->_env('call.dialing', $uid, $extra, $pkt);
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _dial_end(array $pkt): array
    {
        $uid    = $pkt['Uniqueid'] ?? '';
        $status = strtoupper($pkt['DialStatus'] ?? '');
        $ok     = ($status === 'ANSWER');

        $extra = [
            'to_state'    => $ok ? 'BRIDGED' : 'FAILED',
            'dial_status' => $status,
        ];

        $event = $ok ? 'call.answered' : 'call.failed';
        $agent = $this->_agent_chan($pkt['Channel'] ?? '');
        $entry = $this->_env($event, $uid, $extra, $pkt);
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _bridge_enter(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']       ?? '';
        $bridge= $pkt['BridgeUniqueid'] ?? '';

        $extra = ['to_state' => 'BRIDGED', 'bridge_id' => $bridge];
        $entry = $this->_env('call.bridged', $uid, $extra, $pkt);

        $call  = $this->call_cache[$uid] ?? [];
        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _bridge_leave(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']       ?? '';
        $bridge= $pkt['BridgeUniqueid'] ?? '';

        $extra = ['to_state' => 'UNBRIDGED', 'bridge_id' => $bridge];
        $entry = $this->_env('call.unbridged', $uid, $extra, $pkt);

        $call  = $this->call_cache[$uid] ?? [];
        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _hold(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = ['to_state' => 'ON_HOLD', 'held_at' => (string) time()];
        $entry = $this->_env('call.on_hold', $uid, $extra, $pkt);

        $call  = $this->call_cache[$uid] ?? [];
        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _unhold(array $pkt): array
    {
        $uid        = $pkt['Uniqueid'] ?? '';
        $call       = $this->call_cache[$uid] ?? [];
        $held_since = (int) ($call['held_since'] ?? time());
        $hold_dur   = max(0, time() - $held_since);

        $extra = ['to_state' => 'BRIDGED', 'hold_duration_sec' => (string) $hold_dur];
        $entry = $this->_env('call.unhold', $uid, $extra, $pkt);

        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _blind_transfer(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $call  = $this->call_cache[$uid] ?? [];

        $extra = [
            'to_state'           => 'TRANSFERRING',
            'transfer_exten'     => $pkt['Extension']        ?? '',
            'transferee_channel' => $pkt['TransfereeChannel'] ?? '',
            'xfer_seq'           => (string) ((int) ($call['xfer_count'] ?? 0) + 1),
        ];

        $entry = $this->_env('call.blind_transfer', $uid, $extra, $pkt);
        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _attended_transfer(array $pkt): array
    {
        $uid    = $pkt['Uniqueid'] ?? '';
        $result = strtolower($pkt['Result'] ?? '');

        [$event, $to] = match ($result) {
            'transferred' => ['call.attended_transfer', 'TRANSFERRED'],
            'cancelled'   => ['call.consult_cancelled', 'BRIDGED'],
            default       => ['call.attended_transfer', 'TRANSFERRED'],
        };

        $call  = $this->call_cache[$uid] ?? [];
        $extra = [
            'to_state'           => $to,
            'transfer_result'    => $result,
            'transfer_target'    => $pkt['TransferTargetChannel'] ?? '',
            'transferee_channel' => $pkt['TransfereeChannel']     ?? '',
        ];

        $entry = $this->_env($event, $uid, $extra, $pkt);
        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent);
        return $entry;
    }

    private function _hangup(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = [
            'to_state'     => 'ENDED',
            'hangup_cause' => $pkt['Cause']     ?? '',
            'hangup_txt'   => $pkt['Cause-txt'] ?? '',
        ];

        $entry = $this->_env('call.ended', $uid, $extra, $pkt);
        $call  = $this->call_cache[$uid] ?? [];
        $agent = $this->_agent_id((int) ($call['agent_id'] ?? 0));
        $entry['targets'] = $this->_targets_call($agent, true);  // include broadcast on hangup
        return $entry;
    }

    // ── Agent events ──────────────────────────────────────────────────────────

    private function _device_state(array $pkt): array
    {
        $device = $pkt['Device'] ?? '';
        $state  = $pkt['State']  ?? '';

        $ext = '';
        if (preg_match('/PJSIP\/(\d+)/i', $device, $m)) {
            $ext = $m[1];
        }

        $event = $this->_device_event($state);
        $agent = $this->_agent_ext($ext);

        $extra = [
            'call_uid'     => '',
            'session_id'   => '',
            'agent_ext'    => $ext,
            'device'       => $device,
            'device_state' => $state,
        ];

        $entry = $this->_env($event, '', $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['agent_name'] = $agent['fullname'] ?? '';
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }

        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _peer_status(array $pkt): array
    {
        $peer   = $pkt['Peer']       ?? '';
        $status = $pkt['PeerStatus'] ?? '';

        $ext = '';
        if (preg_match('/PJSIP\/(\d+)/i', $peer, $m)) {
            $ext = $m[1];
        }

        $event = ($status === 'Registered') ? 'agent.online' : 'agent.offline';
        $agent = $this->_agent_ext($ext);

        $extra = [
            'call_uid'    => '',
            'session_id'  => '',
            'agent_ext'   => $ext,
            'peer'        => $peer,
            'peer_status' => $status,
        ];

        $entry = $this->_env($event, '', $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['agent_name'] = $agent['fullname'] ?? '';
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }

        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    // ── Queue events ──────────────────────────────────────────────────────────

    private function _queue_caller_join(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = [
            'queue_name' => $pkt['Queue']       ?? '',
            'position'   => $pkt['Position']    ?? '',
            'callerid'   => $pkt['CallerIDNum'] ?? '',
            'count'      => $pkt['Count']       ?? '',
        ];
        $entry = $this->_env('queue.caller_join', $uid, $extra, $pkt);
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _queue_caller_leave(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = [
            'queue_name' => $pkt['Queue']    ?? '',
            'position'   => $pkt['Position'] ?? '',
            'count'      => $pkt['Count']    ?? '',
        ];
        $entry = $this->_env('queue.caller_leave', $uid, $extra, $pkt);
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _queue_caller_abandon(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = [
            'queue_name'        => $pkt['Queue']           ?? '',
            'position'          => $pkt['Position']        ?? '',
            'original_position' => $pkt['OriginalPosition']?? '',
            'hold_time'         => $pkt['HoldTime']        ?? '',
        ];
        $entry = $this->_env('queue.caller_abandon', $uid, $extra, $pkt);
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _agent_called(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']  ?? '';
        $ext   = $pkt['MemberName']?? '';
        $agent = $this->_agent_ext($ext);
        $extra = [
            'queue_name'  => $pkt['Queue'] ?? '',
            'member_name' => $ext,
        ];

        $entry = $this->_env('queue.agent_ringing', $uid, $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _agent_connect(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']  ?? '';
        $ext   = $pkt['MemberName']?? '';
        $agent = $this->_agent_ext($ext);
        $extra = [
            'queue_name'  => $pkt['Queue']    ?? '',
            'hold_time'   => $pkt['HoldTime'] ?? '',
            'member_name' => $ext,
        ];

        $entry = $this->_env('queue.agent_connect', $uid, $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _agent_complete(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']  ?? '';
        $ext   = $pkt['MemberName']?? '';
        $agent = $this->_agent_ext($ext);
        $extra = [
            'queue_name'  => $pkt['Queue']    ?? '',
            'hold_time'   => $pkt['HoldTime'] ?? '',
            'talk_time'   => $pkt['TalkTime'] ?? '',
            'reason'      => $pkt['Reason']   ?? '',
            'member_name' => $ext,
        ];

        $entry = $this->_env('queue.agent_complete', $uid, $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _agent_ring_noanswer(array $pkt): array
    {
        $uid   = $pkt['Uniqueid']  ?? '';
        $ext   = $pkt['MemberName']?? '';
        $agent = $this->_agent_ext($ext);
        $extra = [
            'queue_name'  => $pkt['Queue']    ?? '',
            'ring_time'   => $pkt['RingTime'] ?? '',
            'member_name' => $ext,
        ];

        $entry = $this->_env('queue.agent_ring_noanswer', $uid, $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _queue_member_status(array $pkt): array
    {
        $ext    = $pkt['MemberName'] ?? '';
        $agent  = $this->_agent_ext($ext);
        $code   = (int) ($pkt['Status'] ?? 0);
        $extra  = [
            'call_uid'    => '',
            'session_id'  => '',
            'queue_name'  => $pkt['Queue'] ?? '',
            'member_name' => $ext,
            'status_code' => (string) $code,
            'status_text' => self::$queue_status_map[$code] ?? 'UNKNOWN',
            'paused'      => $pkt['Paused'] ?? '0',
        ];

        $entry = $this->_env('queue.member_status', '', $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    private function _queue_member_pause(array $pkt): array
    {
        $ext    = $pkt['MemberName'] ?? '';
        $paused = ($pkt['Paused'] ?? '0') === '1';
        $agent  = $this->_agent_ext($ext);
        $extra  = [
            'call_uid'    => '',
            'session_id'  => '',
            'queue_name'  => $pkt['Queue']         ?? '',
            'member_name' => $ext,
            'reason'      => $pkt['PausedReason']  ?? '',
        ];

        $event = $paused ? 'queue.member_paused' : 'queue.member_unpaused';
        $entry = $this->_env($event, '', $extra, $pkt);
        if ($agent) {
            $entry['agent_id']   = (string) $agent['id'];
            $entry['agent_ext']  = $agent['extension'];
            $entry['staff_id']   = (string) ($agent['staff_id'] ?? '');
        }
        $entry['targets'] = json_encode(['role:supervisor']);
        return $entry;
    }

    // ── Recording events ──────────────────────────────────────────────────────

    private function _recording_started(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = [
            'recording_file'  => $pkt['TargetFileName'] ?? $pkt['File'] ?? '',
            'recording_state' => 'RECORDING_STARTED',
        ];
        $entry = $this->_env('recording.started', $uid, $extra, $pkt);
        $entry['targets'] = json_encode([]);  // no UI in Phase 2
        return $entry;
    }

    private function _recording_mute(array $pkt): array
    {
        $uid    = $pkt['Uniqueid']      ?? '';
        $muted  = ($pkt['MuteDirection']?? '') !== '';
        $extra  = [
            'recording_state'  => $muted ? 'RECORDING_PAUSED' : 'RECORDING_RESUMED',
            'mute_direction'   => $pkt['MuteDirection'] ?? '',
        ];

        $event = $muted ? 'recording.paused' : 'recording.resumed';
        $entry = $this->_env($event, $uid, $extra, $pkt);
        $entry['targets'] = json_encode([]);
        return $entry;
    }

    private function _recording_stopped(array $pkt): array
    {
        $uid   = $pkt['Uniqueid'] ?? '';
        $extra = [
            'recording_file'  => $pkt['File'] ?? '',
            'recording_state' => 'RECORDING_STOPPED',
        ];
        $entry = $this->_env('recording.stopped', $uid, $extra, $pkt);
        $entry['targets'] = json_encode([]);
        return $entry;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function _agent_chan(string $channel): ?array
    {
        if (preg_match('/PJSIP\/(\d+)/i', $channel, $m)) {
            return $this->_agent_ext($m[1]);
        }
        return null;
    }

    private function _agent_ext(string $ext): ?array
    {
        return $this->ext_map[$ext] ?? null;
    }

    private function _agent_id(int $id): ?array
    {
        if ($id <= 0) return null;
        foreach ($this->ext_map as $agent) {
            if ((int) $agent['id'] === $id) return $agent;
        }
        return null;
    }

    private function _targets_call(?array $agent, bool $broadcast = false): string
    {
        $t = ['role:supervisor'];
        if ($agent && !empty($agent['staff_id'])) {
            $t[] = 'staff:' . $agent['staff_id'];
        }
        if ($broadcast) {
            $t[] = 'broadcast';
        }
        return json_encode(array_unique($t));
    }

    private function _direction(string $src, string $dst, string $context): string
    {
        $src_agent = isset($this->ext_map[$src]);
        $dst_agent = isset($this->ext_map[$dst]);

        if ($src_agent && $dst_agent) return 'internal';
        if ($context === 'from-trunk')  return 'inbound';
        if ($src_agent || $context === 'from-internal') return 'outbound';
        if ($dst_agent) return 'inbound';
        if (strlen($src) <= 4) return 'outbound';
        return 'inbound';
    }

    private function _device_event(string $state): string
    {
        return match (strtoupper($state)) {
            'INUSE', 'RINGINUSE', 'ONHOLD' => 'agent.busy',
            'RINGING'     => 'agent.ringing',
            'NOT_INUSE'   => 'agent.ready',
            'UNAVAILABLE' => 'agent.offline',
            'PAUSED'      => 'agent.paused',
            default       => 'agent.ready',
        };
    }
}
