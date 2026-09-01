<?php

/**
 * Pure call state machine. No I/O. No Redis. No DB.
 * Input: current state + event_type → new state or null (invalid transition).
 *
 * State constants match the to_state/from_state fields written into Redis Streams.
 */
class Call_state_machine
{
    const INIT         = 'INIT';
    const RINGING      = 'RINGING';
    const DIALING      = 'DIALING';
    const BRIDGED      = 'BRIDGED';
    const ON_HOLD      = 'ON_HOLD';
    const CONSULT      = 'CONSULT';
    const TRANSFERRING = 'TRANSFERRING';
    const TRANSFERRED  = 'TRANSFERRED';
    const UNBRIDGED    = 'UNBRIDGED';
    const ENDED        = 'ENDED';
    const FAILED       = 'FAILED';

    const TERMINAL = [self::ENDED, self::FAILED, self::TRANSFERRED];

    // [from_state][event_type] => to_state
    private static $map = [
        '' => [
            'call.ringing' => self::RINGING,
        ],
        self::INIT => [
            'call.ringing'  => self::RINGING,
            'call.dialing'  => self::DIALING,
            'call.ended'    => self::ENDED,
            'call.failed'   => self::FAILED,
        ],
        self::RINGING => [
            'call.dialing'  => self::DIALING,
            'call.answered' => self::BRIDGED,
            'call.bridged'  => self::BRIDGED,
            'call.ended'    => self::ENDED,
            'call.failed'   => self::FAILED,
        ],
        self::DIALING => [
            'call.answered' => self::BRIDGED,
            'call.bridged'  => self::BRIDGED,
            'call.failed'   => self::FAILED,
            'call.ended'    => self::ENDED,
        ],
        self::BRIDGED => [
            'call.on_hold'          => self::ON_HOLD,
            'call.blind_transfer'   => self::TRANSFERRING,
            'call.consult_started'  => self::CONSULT,
            'call.unbridged'        => self::UNBRIDGED,
            'call.ended'            => self::ENDED,
        ],
        self::ON_HOLD => [
            'call.unhold'   => self::BRIDGED,
            'call.ended'    => self::ENDED,
        ],
        self::CONSULT => [
            'call.attended_transfer' => self::TRANSFERRED,
            'call.consult_cancelled' => self::BRIDGED,
            'call.ended'             => self::ENDED,
        ],
        self::TRANSFERRING => [
            'call.attended_transfer' => self::TRANSFERRED,
            'call.ended'             => self::ENDED,
        ],
        self::UNBRIDGED => [
            'call.bridged'  => self::BRIDGED,
            'call.ended'    => self::ENDED,
        ],
    ];

    /**
     * Attempt transition. Returns new state on success, null on invalid/terminal.
     */
    public static function transition(string $from, string $event_type): ?string
    {
        // call.ended is unconditionally terminal from any non-terminal state
        if ($event_type === 'call.ended') {
            return in_array($from, self::TERMINAL, true) ? null : self::ENDED;
        }

        if ($event_type === 'call.failed') {
            return in_array($from, self::TERMINAL, true) ? null : self::FAILED;
        }

        if (in_array($from, self::TERMINAL, true)) {
            return null;
        }

        return self::$map[$from][$event_type] ?? null;
    }

    public static function is_terminal(string $state): bool
    {
        return in_array($state, self::TERMINAL, true);
    }

    public static function all_states(): array
    {
        return [
            self::INIT, self::RINGING, self::DIALING, self::BRIDGED,
            self::ON_HOLD, self::CONSULT, self::TRANSFERRING, self::TRANSFERRED,
            self::UNBRIDGED, self::ENDED, self::FAILED,
        ];
    }

    /**
     * Map AMI DeviceState string to agent presence event type.
     */
    public static function device_state_to_event(string $ami_state): string
    {
        switch (strtoupper($ami_state)) {
            case 'INUSE':       return 'agent.busy';
            case 'RINGING':     return 'agent.ringing';
            case 'RINGINUSE':   return 'agent.busy';
            case 'ONHOLD':      return 'agent.busy';
            case 'NOT_INUSE':   return 'agent.ready';
            case 'UNAVAILABLE': return 'agent.offline';
            case 'PAUSED':      return 'agent.paused';
            default:            return 'agent.ready';
        }
    }

    /**
     * Map SalesOS agent event type to presence enum value for DB/Redis.
     */
    public static function event_to_presence(string $event_type): string
    {
        $map = [
            'agent.online'   => 'ONLINE',
            'agent.offline'  => 'OFFLINE',
            'agent.ready'    => 'READY',
            'agent.busy'     => 'BUSY',
            'agent.ringing'  => 'RINGING',
            'agent.paused'   => 'PAUSED',
            'agent.wrapup'   => 'WRAPUP',
            'agent.break'    => 'BREAK',
            'agent.lunch'    => 'LUNCH',
            'agent.meeting'  => 'MEETING',
        ];
        return $map[$event_type] ?? 'OFFLINE';
    }
}
